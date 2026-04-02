<?php

declare(strict_types=1);

namespace FairPulse\Utils;

use FairPulse\Interfaces\LoggerInterface;

final class GitHubLogger implements LoggerInterface
{
    public function notice(string $message): void
    {
        echo "::notice::{$message}\n";
    }

    public function warning(string $message): void
    {
        echo "::warning::{$message}\n";
    }

    public function error(string $message): void
    {
        echo "::error::{$message}\n";
    }

    public function raw(string $message): void
    {
        echo $message;
    }

    public function group(string $title): void
    {
        echo "::group::{$title}\n";
    }

    public function endGroup(): void
    {
        echo "::endgroup::\n";
    }
}
