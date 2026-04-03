<?php

declare(strict_types=1);

namespace FairPulse\Utils;

use FairPulse\Core\Env;
use FairPulse\Interfaces\OutputWriterInterface;

final class GitHubOutputWriter implements OutputWriterInterface
{
    public function __construct(private readonly Env $env)
    {
    }

    public function write(string $name, string $value, bool $multiline = false): void
    {
        $outputFile = $this->env->get('GITHUB_OUTPUT');
        if ($outputFile === null || $outputFile === '') {
            return;
        }

        if ($multiline) {
            file_put_contents($outputFile, "{$name}<<EOF\n{$value}\nEOF\n", FILE_APPEND);
            return;
        }

        file_put_contents($outputFile, "{$name}={$value}\n", FILE_APPEND);
    }
}
