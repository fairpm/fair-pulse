<?php

declare(strict_types=1);

namespace FairPulse\Actions;

use FairPulse\Core\ActionRuntime;

final class PublishFairAction
{
    private const VERSION_REGEX = '/^v?[0-9]+\.[0-9]+\.[0-9]+(?:[-+][0-9A-Za-z.-]+)?$/';

    private ActionRuntime $runtime;

    public function __construct(?ActionRuntime $runtime = null)
    {
        $this->runtime = $runtime ?? new ActionRuntime();
    }

    public function run(): int
    {
        try {
            $this->runtime->logger()->group('FAIR Pulse Action');

            $repo = $this->requireEnv('GITHUB_REPOSITORY', 'GITHUB_REPOSITORY is required');
            $serverUrl = $this->requireEnv('GITHUB_SERVER_URL', 'GITHUB_SERVER_URL is required');
            $this->requireEnv('GITHUB_TOKEN', 'GITHUB_TOKEN is required for release download/upload');

            $repoUrl = rtrim($serverUrl, '/') . '/' . $repo;
            $version = $this->resolveVersion();
            $artifactName = $this->resolveArtifactName($repo);
            $uploadMetadata = $this->resolveBoolInput('INPUT_UPLOAD_METADATA', true);
            $updateDidService = $this->resolveBoolInput('INPUT_UPDATE_DID_SERVICE', true);

            $this->runtime->logger()->notice('Resolved version: ' . $version);
            $this->runtime->logger()->notice('Resolved artifact name: ' . $artifactName);

            $keysOutputs = $this->runActionWithOutputs(
                __DIR__ . '/ManageKeysAction.php',
                [
                    'FAIR_ROTATION_KEY_PRIVATE' => $this->runtime->env()->get('FAIR_ROTATION_KEY_PRIVATE') ?? '',
                    'FAIR_ROTATION_KEY_PUBLIC' => $this->runtime->env()->get('FAIR_ROTATION_KEY_PUBLIC') ?? '',
                    'FAIR_VERIFICATION_KEY_PRIVATE' => $this->runtime->env()->get('FAIR_VERIFICATION_KEY_PRIVATE') ?? '',
                    'FAIR_VERIFICATION_KEY_PUBLIC' => $this->runtime->env()->get('FAIR_VERIFICATION_KEY_PUBLIC') ?? '',
                    'FAIR_DID' => $this->runtime->env()->get('FAIR_DID') ?? '',
                ],
                'Manage Keys'
            );

            if (($keysOutputs['keys_exist'] ?? 'false') !== 'true') {
                throw new \RuntimeException('Required FAIR keys are missing.');
            }

            $didOutputs = $this->runActionWithOutputs(
                __DIR__ . '/CreateDidAction.php',
                [
                    'ROTATION_PRIVATE' => $keysOutputs['rotation_private'] ?? '',
                    'ROTATION_PUBLIC' => $keysOutputs['rotation_public'] ?? '',
                    'VERIFICATION_PRIVATE' => $keysOutputs['verification_private'] ?? '',
                    'VERIFICATION_PUBLIC' => $keysOutputs['verification_public'] ?? '',
                    'EXISTING_DID' => $keysOutputs['did'] ?? '',
                    'DID_EXISTS' => $keysOutputs['did_exists'] ?? 'false',
                    'REPO_URL' => $repoUrl,
                ],
                'Create DID'
            );

            $did = $didOutputs['did'] ?? '';
            if ($did === '') {
                throw new \RuntimeException('DID generation failed: missing DID output.');
            }

            $artifactPath = '/tmp/' . $artifactName;
            $this->downloadReleaseArtifact($version, $artifactName, $artifactPath);

            $signOutputs = $this->runActionWithOutputs(
                __DIR__ . '/SignArtifactAction.php',
                [
                    'VERIFICATION_PRIVATE' => $keysOutputs['verification_private'] ?? '',
                    'VERIFICATION_PUBLIC' => $keysOutputs['verification_public'] ?? '',
                    'ARTIFACT_PATH' => $artifactPath,
                ],
                'Sign Artifact'
            );

            $signature = $signOutputs['signature'] ?? '';
            $checksum = $signOutputs['checksum'] ?? '';
            if ($signature === '' || $checksum === '') {
                throw new \RuntimeException('Artifact signing failed: missing signature/checksum output.');
            }

            $metadataOutputs = $this->runActionWithOutputs(
                __DIR__ . '/GenerateMetadataAction.php',
                [
                    'DID' => $did,
                    'VERSION' => $version,
                    'CHECKSUM' => $checksum,
                    'SIGNATURE' => $signature,
                    'ARTIFACT_PATH' => $artifactPath,
                    'VERIFICATION_PUBLIC' => $keysOutputs['verification_public'] ?? '',
                    'REPO_URL' => $repoUrl,
                    'GITHUB_WORKSPACE' => $this->runtime->env()->get('GITHUB_WORKSPACE') ?? getcwd(),
                ],
                'Generate Metadata'
            );

            $metadataPath = $metadataOutputs['metadata_path'] ?? '/tmp/fair-metadata.json';

            if ($uploadMetadata) {
                $this->uploadMetadata($version, $metadataPath);
            } else {
                $this->runtime->logger()->notice('Skipping metadata upload (upload-metadata=false).');
            }

            if ($updateDidService) {
                $metadataUrl = rtrim($repoUrl, '/') . '/releases/download/' . $version . '/fair-metadata.json';
                $this->runActionWithOutputs(
                    __DIR__ . '/UpdateDidServiceAction.php',
                    [
                        'DID' => $did,
                        'ROTATION_PRIVATE' => $keysOutputs['rotation_private'] ?? '',
                        'METADATA_URL' => $metadataUrl,
                        'PREV_CID' => $didOutputs['cid'] ?? '',
                    ],
                    'Update DID Service'
                );
            } else {
                $this->runtime->logger()->notice('Skipping DID update (update-did-service=false).');
            }

            $this->runtime->output()->write('version', $version);
            $this->runtime->output()->write('did', $did);
            $this->runtime->output()->write('artifact_path', $artifactPath);
            $this->runtime->output()->write('metadata_path', $metadataPath);

            $this->runtime->logger()->notice('FAIR Pulse completed successfully.');
            $this->runtime->logger()->endGroup();

            return 0;
        } catch (\Throwable $throwable) {
            $this->runtime->logger()->error('Publish failed: ' . $throwable->getMessage());
            $this->runtime->logger()->endGroup();

            return 1;
        }
    }

    private function resolveVersion(): string
    {
        $input = trim($this->runtime->env()->get('INPUT_VERSION') ?? '');
        if ($input !== '') {
            $this->assertValidVersion($input);

            return $input;
        }

        $refType = $this->runtime->env()->get('GITHUB_REF_TYPE') ?? '';
        $refName = trim($this->runtime->env()->get('GITHUB_REF_NAME') ?? '');
        if ($refType === 'tag' && $refName !== '') {
            $this->assertValidVersion($refName);

            return $refName;
        }

        $tag = trim($this->runCommand('git describe --tags --abbrev=0', 'Resolve latest tag', true));
        if ($tag !== '') {
            $this->assertValidVersion($tag);

            return $tag;
        }

        throw new \RuntimeException('Unable to determine version. Provide input "version" or run from a tagged ref.');
    }

    private function resolveArtifactName(string $repo): string
    {
        $input = trim($this->runtime->env()->get('INPUT_ARTIFACT_NAME') ?? '');
        $artifactName = $input !== '' ? $input : basename($repo) . '.zip';

        if (!preg_match('/^[A-Za-z0-9._-]+\.zip$/', $artifactName)) {
            throw new \RuntimeException('Invalid artifact-name input. Expected filename ending with .zip');
        }

        return $artifactName;
    }

    private function resolveBoolInput(string $name, bool $default): bool
    {
        $value = strtolower(trim($this->runtime->env()->get($name) ?? ''));
        if ($value === '') {
            return $default;
        }

        if ($value === 'true') {
            return true;
        }

        if ($value === 'false') {
            return false;
        }

        throw new \RuntimeException("Invalid boolean input for {$name}. Use true or false.");
    }

    private function assertValidVersion(string $version): void
    {
        if (!preg_match(self::VERSION_REGEX, $version)) {
            throw new \RuntimeException('Invalid version format: ' . $version . '. Use semver like v1.2.3');
        }
    }

    private function requireEnv(string $name, string $error): string
    {
        $value = $this->runtime->env()->get($name);
        if ($value === null || $value === '') {
            throw new \RuntimeException($error);
        }

        return $value;
    }

    private function downloadReleaseArtifact(string $version, string $artifactName, string $artifactPath): void
    {
        $this->runtime->logger()->group('Download release artifact');
        $this->runCommand(
            'gh release download ' . escapeshellarg($version) . ' -p ' . escapeshellarg($artifactName) . ' -O ' . escapeshellarg($artifactPath),
            'Download release artifact with gh'
        );

        if (!file_exists($artifactPath)) {
            throw new \RuntimeException('Artifact download failed: file not found at ' . $artifactPath);
        }

        $this->runtime->logger()->notice('Artifact downloaded: ' . $artifactPath);
        $this->runtime->logger()->endGroup();
    }

    private function uploadMetadata(string $version, string $metadataPath): void
    {
        if (!file_exists($metadataPath)) {
            throw new \RuntimeException('Metadata upload failed: metadata file not found at ' . $metadataPath);
        }

        $this->runtime->logger()->group('Upload metadata');
        $this->runCommand(
            'gh release upload ' . escapeshellarg($version) . ' ' . escapeshellarg($metadataPath) . ' --clobber',
            'Upload FAIR metadata to release'
        );
        $this->runtime->logger()->endGroup();
    }

    /**
     * @return array<string, string>
     */
    private function runActionWithOutputs(string $scriptPath, array $env, string $label): array
    {
        $this->runtime->logger()->group($label);

        $outputFile = tempnam(sys_get_temp_dir(), 'fair-output-');
        if ($outputFile === false) {
            throw new \RuntimeException('Could not create temporary output file for action step.');
        }

        $mergedEnv = array_merge($_SERVER, $_ENV, $env, ['GITHUB_OUTPUT' => $outputFile]);

        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($scriptPath);
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(['bash', '-c', $command], $descriptorSpec, $pipes, getcwd() ?: null, $mergedEnv);
        if (!is_resource($process)) {
            throw new \RuntimeException('Failed to start step: ' . $label);
        }

        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($stdout !== '') {
            $this->runtime->logger()->raw($stdout);
        }

        if ($stderr !== '') {
            $this->runtime->logger()->warning(trim($stderr));
        }

        if ($exitCode !== 0) {
            throw new \RuntimeException($label . ' failed with exit code ' . $exitCode);
        }

        $parsed = $this->parseGitHubOutputFile($outputFile);
        @unlink($outputFile);

        $this->runtime->logger()->endGroup();

        return $parsed;
    }

    /**
     * @return array<string, string>
     */
    private function parseGitHubOutputFile(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return [];
        }

        $outputs = [];
        $count = count($lines);
        for ($i = 0; $i < $count; $i++) {
            $line = $lines[$i];
            if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)<<(.+)$/', $line, $matches) === 1) {
                $key = $matches[1];
                $delimiter = $matches[2];
                $buffer = [];
                $i++;
                while ($i < $count && $lines[$i] !== $delimiter) {
                    $buffer[] = $lines[$i];
                    $i++;
                }
                $outputs[$key] = implode("\n", $buffer);
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $outputs[$parts[0]] = $parts[1];
            }
        }

        return $outputs;
    }

    private function runCommand(string $command, string $label, bool $ignoreFailure = false): string
    {
        $this->runtime->logger()->notice($label);

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open(['bash', '-c', $command], $descriptorSpec, $pipes, getcwd() ?: null);
        if (!is_resource($process)) {
            throw new \RuntimeException('Failed to start command: ' . $label);
        }

        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($stdout !== '') {
            $this->runtime->logger()->raw($stdout);
        }
        if ($stderr !== '') {
            $this->runtime->logger()->warning(trim($stderr));
        }

        if ($exitCode !== 0 && !$ignoreFailure) {
            throw new \RuntimeException($label . ' failed.');
        }

        return $stdout;
    }
}

if (PHP_SAPI === 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    require_once __DIR__ . '/../bootstrap.php';

    $action = new PublishFairAction(new \FairPulse\Core\ActionRuntime());
    exit($action->run());
}
