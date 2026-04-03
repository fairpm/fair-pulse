<?php

declare(strict_types=1);

namespace FairPulse\Interfaces;

interface LoggerInterface
{
    public function notice(string $message): void;

    public function warning(string $message): void;

    public function error(string $message): void;

    public function raw(string $message): void;

    public function group(string $title): void;

    public function endGroup(): void;
}
