<?php

declare(strict_types=1);

namespace FairPulse\Actions;

use FairPulse\Core\ActionRuntime;
use FairPulse\Core\DidManagerBootstrap;
use FairPulse\Services\MetadataGenerationService;

final class GenerateMetadataAction
{
    public function __construct(private readonly ActionRuntime $runtime)
    {
    }

    public function run(): int
    {
        try {
            DidManagerBootstrap::requireAutoload($this->runtime->logger());

            $did = $this->runtime->env()->getRequired('DID', 'DID is required');
            $version = $this->runtime->env()->getRequired('VERSION', 'Version is required');
            $checksum = $this->runtime->env()->get('CHECKSUM') ?? '';
            $signature = $this->runtime->env()->get('SIGNATURE') ?? '';
            $artifactPath = $this->runtime->env()->get('ARTIFACT_PATH') ?? '';
            $repoUrl = $this->runtime->env()->get('REPO_URL') ?? '';
            $workDir = $this->runtime->env()->get('GITHUB_WORKSPACE') ?? '';

            if ($artifactPath === '' || $repoUrl === '' || $workDir === '') {
                throw new \RuntimeException('Artifact path, repository URL, and workspace are required');
            }

            $service = new MetadataGenerationService();
            $metadata = $service->generate($did, $version, $checksum, $signature, $artifactPath, $repoUrl, $workDir);

            $metadataPath = '/tmp/fair-metadata.json';
            file_put_contents($metadataPath, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $this->runtime->logger()->notice('FAIR metadata generated successfully');
            $this->runtime->logger()->raw(json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
            $this->runtime->output()->write('metadata_path', $metadataPath);

            return 0;
        } catch (\RuntimeException $exception) {
            $this->runtime->logger()->error($exception->getMessage());
            return 1;
        }
    }
}

if (PHP_SAPI === 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    require_once __DIR__ . '/../bootstrap.php';

    $action = new GenerateMetadataAction(new \FairPulse\Core\ActionRuntime());
    exit($action->run());
}
