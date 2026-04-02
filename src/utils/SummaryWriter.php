<?php

declare(strict_types=1);

namespace FairPulse\Utils;

use FairPulse\Core\Env;

final class SummaryWriter
{
    public function __construct(private readonly Env $env)
    {
    }

    public function append(string $content): void
    {
        $summaryFile = $this->env->get('GITHUB_STEP_SUMMARY');
        if ($summaryFile === null || $summaryFile === '') {
            return;
        }

        file_put_contents($summaryFile, $content, FILE_APPEND);
    }
}
