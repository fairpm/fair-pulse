<?php

declare(strict_types=1);

namespace FairPulse\Core;

use FairPulse\Interfaces\LoggerInterface;
use FairPulse\Interfaces\OutputWriterInterface;
use FairPulse\Utils\GitHubLogger;
use FairPulse\Utils\GitHubOutputWriter;

final class ActionRuntime
{
    private Env $env;

    private LoggerInterface $logger;

    private OutputWriterInterface $outputWriter;

    public function __construct(?Env $env = null, ?LoggerInterface $logger = null, ?OutputWriterInterface $outputWriter = null)
    {
        $this->env = $env ?? new Env();
        $this->logger = $logger ?? new GitHubLogger();
        $this->outputWriter = $outputWriter ?? new GitHubOutputWriter($this->env);
    }

    public function env(): Env
    {
        return $this->env;
    }

    public function logger(): LoggerInterface
    {
        return $this->logger;
    }

    public function output(): OutputWriterInterface
    {
        return $this->outputWriter;
    }
}
