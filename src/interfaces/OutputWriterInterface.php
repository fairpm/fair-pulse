<?php

declare(strict_types=1);

namespace FairPulse\Interfaces;

interface OutputWriterInterface
{
    public function write(string $name, string $value, bool $multiline = false): void;
}
