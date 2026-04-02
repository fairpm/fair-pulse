<?php

declare(strict_types=1);

namespace FairPulse\Core;

use FairPulse\Interfaces\LoggerInterface;

final class DidManagerBootstrap
{
    public const DEFAULT_AUTOLOAD = '/tmp/did-manager/vendor/autoload.php';

    public static function requireAutoload(LoggerInterface $logger, ?string $autoloadPath = null): void
    {
        $path = $autoloadPath ?? self::DEFAULT_AUTOLOAD;
        if (!file_exists($path)) {
            $logger->error("Autoloader not found at {$path}");
            throw new \RuntimeException('DID manager autoloader missing.');
        }

        require_once $path;
    }
}
