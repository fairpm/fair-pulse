<?php

declare(strict_types=1);

namespace FairPulse\Actions;

use FairPulse\Core\ActionRuntime;
use FairPulse\Core\DidManagerBootstrap;
use FairPulse\Services\DidService;
use FairPulse\Utils\SummaryWriter;

final class CreateDidAction
{
    public function __construct(private readonly ActionRuntime $runtime)
    {
    }

    public function run(): int
    {
        try {
            DidManagerBootstrap::requireAutoload($this->runtime->logger());

            $rotationPrivate = $this->runtime->env()->getRequired('ROTATION_PRIVATE', 'Rotation keys are required');
            $rotationPublic = $this->runtime->env()->getRequired('ROTATION_PUBLIC', 'Rotation keys are required');
            $verificationPrivate = $this->runtime->env()->getRequired('VERIFICATION_PRIVATE', 'Verification keys are required');
            $verificationPublic = $this->runtime->env()->getRequired('VERIFICATION_PUBLIC', 'Verification keys are required');
            $repoUrl = $this->runtime->env()->get('REPO_URL') ?? '';

            if ($repoUrl === '') {
                throw new \RuntimeException('Repository URL is required');
            }

            $didService = new DidService($this->runtime->logger(), new SummaryWriter($this->runtime->env()));
            $result = $didService->createOrReuse(
                $rotationPrivate,
                $verificationPrivate,
                $this->runtime->env()->getBool('DID_EXISTS'),
                $this->runtime->env()->get('EXISTING_DID'),
                $repoUrl,
            );

            $this->runtime->output()->write('did', $result->did);
            $this->runtime->output()->write('created', $result->created ? 'true' : 'false');
            if ($result->cid !== null) {
                $this->runtime->output()->write('cid', $result->cid);
            }

            return 0;
        } catch (\RuntimeException $exception) {
            $this->runtime->logger()->error($exception->getMessage());
            return 1;
        }
    }
}
