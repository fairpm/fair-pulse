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

            $this->runtime->logger()->notice('Resolved version: ' . $version);
            $this->runtime->logger()->notice('Resolved artifact name: ' . $artifactName);

            $keysOutputs = $this->runActionWithOutputs(
                __DIR__ . '/ManageKeysAction.php',
                [
                    'FAIR_VERIFICATION_KEY_PRIVATE' => $this->runtime->env()->get('FAIR_VERIFICATION_KEY_PRIVATE') ?? '',
                    'FAIR_VERIFICATION_KEY_PUBLIC' => $this->runtime->env()->get('FAIR_VERIFICATION_KEY_PUBLIC') ?? '',
                    'FAIR_DID' => $this->runtime->env()->get('FAIR_DID') ?? '',
                ],
                'Manage Keys'
            );

            if (($keysOutputs['keys_exist'] ?? 'false') !== 'true') {
                throw new \RuntimeException('Required FAIR keys are missing.');
            }

            $did = $keysOutputs['did'] ?? '';
            if ($did === '' || ($keysOutputs['did_exists'] ?? 'false') !== 'true') {
                throw new \RuntimeException(
                    'FAIR_DID is required. '
                    . 'Run local setup first: composer fair:setup-local -- https://github.com/<owner>/<repo>'
                );
            }
            $this->runtime->logger()->notice('Using existing DID: ' . $did);
            if ($did === '') {
                throw new \RuntimeException('DID generation failed: missing DID output.');
            }

            $artifactPath = '/tmp/' . $artifactName;
            $releaseTag = $this->ensureReleaseArtifact($repo, $version, $artifactName, $artifactPath);
            if ($releaseTag !== $version) {
                $this->runtime->logger()->notice(
                    'Using normalized release tag: ' . $releaseTag . ' (from ' . $version . ')'
                );
            }

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
                $this->uploadMetadata($repo, $releaseTag, $metadataPath);
            } else {
                $this->runtime->logger()->notice('Skipping metadata upload (upload-metadata=false).');
            }

            $this->runtime->output()->write('version', $releaseTag);
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

    private function ensureReleaseArtifact(string $repo, string $version, string $artifactName, string $artifactPath): string
    {
        $this->runtime->logger()->group('Download release artifact');
        try {
            foreach ($this->releaseTagCandidates($version) as $candidateTag) {
                try {
                    $this->runCommand(
                        'gh release download ' . escapeshellarg($candidateTag)
                        . ' --repo ' . escapeshellarg($repo)
                        . ' -p ' . escapeshellarg($artifactName)
                        . ' -O ' . escapeshellarg($artifactPath),
                        'Download release artifact with gh'
                    );

                    if (!file_exists($artifactPath)) {
                        throw new \RuntimeException('Artifact download failed: file not found at ' . $artifactPath);
                    }

                    $this->runtime->logger()->notice('Artifact downloaded: ' . $artifactPath);

                    return $candidateTag;
                } catch (\RuntimeException $runtimeException) {
                    $this->runtime->logger()->notice(
                        'Release tag not found or missing asset for tag: ' . $candidateTag
                    );
                }
            }

            $this->runtime->logger()->notice(
                'No matching release asset found. Building and uploading artifact from workspace.'
            );

            $workspace = $this->requireEnv(
                'GITHUB_WORKSPACE',
                'GITHUB_WORKSPACE is required to build a missing release artifact.'
            );

            $workspaceArtifactPath = rtrim($workspace, '/') . '/' . $artifactName;
            if (is_file($workspaceArtifactPath)) {
                if (!copy($workspaceArtifactPath, $artifactPath)) {
                    throw new \RuntimeException(
                        'Failed to copy workspace artifact from ' . $workspaceArtifactPath
                    );
                }

                $this->runtime->logger()->notice('Using workspace artifact: ' . $workspaceArtifactPath);
            } else {
                $this->buildArtifactFromWorkspace($workspace, $artifactPath);
            }

            $releaseTag = $this->ensureReleaseExists($repo, $version);
            $this->runCommand(
                'gh release upload ' . escapeshellarg($releaseTag)
                . ' --repo ' . escapeshellarg($repo)
                . ' ' . escapeshellarg($artifactPath)
                . ' --clobber',
                'Upload generated artifact to release'
            );

            return $releaseTag;
        } finally {
            $this->runtime->logger()->endGroup();
        }
    }

    private function buildArtifactFromWorkspace(string $workspace, string $artifactPath): void
    {
        if (!is_dir($workspace)) {
            throw new \RuntimeException('Workspace path does not exist: ' . $workspace);
        }

        $this->runCommand(
            'rm -f ' . escapeshellarg($artifactPath),
            'Remove stale artifact archive before rebuild',
            true
        );

        $command = 'git -C ' . escapeshellarg($workspace)
            . ' archive --format=zip --output=' . escapeshellarg($artifactPath) . ' HEAD';

        $this->runCommand($command, 'Build artifact archive from workspace with git archive');

        if (!is_file($artifactPath)) {
            throw new \RuntimeException('Failed to build artifact archive at ' . $artifactPath);
        }

        $this->runtime->logger()->notice('Built artifact archive: ' . $artifactPath);
    }

    private function ensureReleaseExists(string $repo, string $version): string
    {
        foreach ($this->releaseTagCandidates($version) as $candidateTag) {
            try {
                $this->runCommand(
                    'gh release view ' . escapeshellarg($candidateTag)
                    . ' --repo ' . escapeshellarg($repo),
                    'Check release exists for tag ' . $candidateTag
                );

                return $candidateTag;
            } catch (\RuntimeException $runtimeException) {
                $this->runtime->logger()->notice('Release not found for tag: ' . $candidateTag);
            }
        }

        $releaseTag = $this->releaseTagCandidates($version)[0] ?? $version;
        $createCommand = 'gh release create ' . escapeshellarg($releaseTag)
            . ' --repo ' . escapeshellarg($repo)
            . ' --title ' . escapeshellarg($releaseTag)
            . ' --notes ' . escapeshellarg('Release created by FAIR Pulse');

        $sha = trim((string) ($this->runtime->env()->get('GITHUB_SHA') ?? ''));
        if ($sha !== '') {
            $createCommand .= ' --target ' . escapeshellarg($sha);
        }

        $this->runCommand($createCommand, 'Create release for missing tag ' . $releaseTag);

        return $releaseTag;
    }

    private function uploadMetadata(string $repo, string $version, string $metadataPath): void
    {
        if (!file_exists($metadataPath)) {
            throw new \RuntimeException('Metadata upload failed: metadata file not found at ' . $metadataPath);
        }

        $this->runtime->logger()->group('Upload metadata');
        $this->runCommand(
            'gh release upload ' . escapeshellarg($version)
            . ' --repo ' . escapeshellarg($repo)
            . ' ' . escapeshellarg($metadataPath)
            . ' --clobber',
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

        $mergedEnv = $this->buildProcessEnv(array_merge($env, ['GITHUB_OUTPUT' => $outputFile]));

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

    /**
     * @return list<string>
     */
    private function releaseTagCandidates(string $version): array
    {
        $normalized = trim($version);
        if ($normalized === '') {
            return [];
        }

        $candidates = [$normalized];
        if (str_starts_with($normalized, 'v')) {
            $withoutPrefix = ltrim($normalized, 'v');
            if ($withoutPrefix !== '') {
                $candidates[] = $withoutPrefix;
            }
        } else {
            $candidates[] = 'v' . $normalized;
        }

        return array_values(array_unique($candidates));
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, string>
     */
    private function buildProcessEnv(array $overrides): array
    {
        $merged = array_merge($_SERVER, $_ENV, $overrides);
        $normalized = [];

        foreach ($merged as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $normalized[$key] = (string) $value;
                continue;
            }

            if ($value instanceof \Stringable) {
                $normalized[$key] = (string) $value;
            }
        }

        return $normalized;
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
