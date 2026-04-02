<?php

declare(strict_types=1);

namespace FairPulse\Actions;

use FairPulse\Core\ActionRuntime;
use FairPulse\Core\DidManagerBootstrap;
use FairPulse\Services\ArtifactSigningService;

final class SignArtifactAction
{
    public function __construct(private readonly ActionRuntime $runtime)
    {
    }

    public function run(): int
    {
        try {
            DidManagerBootstrap::requireAutoload($this->runtime->logger());

            $verificationPrivate = $this->runtime->env()->getRequired('VERIFICATION_PRIVATE', 'Verification keys are required');
            $verificationPublic = $this->runtime->env()->getRequired('VERIFICATION_PUBLIC', 'Verification keys are required');
            $artifactPath = $this->runtime->env()->get('ARTIFACT_PATH') ?? '';

            if ($artifactPath === '' || !file_exists($artifactPath)) {
                throw new \RuntimeException("Artifact path is invalid or file does not exist: {$artifactPath}");
            }

            $service = new ArtifactSigningService();
            $result = $service->sign($verificationPrivate, $artifactPath);

            $this->runtime->logger()->notice('Package signed successfully');
            $this->runtime->output()->write('signature', $result->signature);
            $this->runtime->output()->write('checksum', $result->checksum);

            return 0;
        } catch (\RuntimeException $exception) {
            $this->runtime->logger()->error($exception->getMessage());
            return 1;
        }
    }
}
