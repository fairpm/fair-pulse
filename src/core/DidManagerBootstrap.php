<?php

declare(strict_types=1);

namespace FairPulse\Core;

use FAIR\DID\Crypto\DidCodec;
use FairPulse\Interfaces\LoggerInterface;

final class DidManagerBootstrap
{
    public const LEGACY_FALLBACK_AUTOLOAD = '/tmp/did-manager/vendor/autoload.php';
    private const FORCE_STUB_ENV = 'FAIR_TEST_STUB_DID_MANAGER';

    public static function requireAutoload(LoggerInterface $logger, ?string $autoloadPath = null): void
    {
        $forceStub = getenv(self::FORCE_STUB_ENV) === 'true';
        $path = $autoloadPath ?? self::LEGACY_FALLBACK_AUTOLOAD;
        if ($forceStub) {
            if (!file_exists($path)) {
                $logger->error('Forced DID manager stub autoloader missing at ' . $path);
                throw new \RuntimeException('DID manager test stub missing.');
            }

            require_once $path;

            if (!class_exists(DidCodec::class, false)) {
                $logger->error('DID manager stub did not expose expected classes.');
                throw new \RuntimeException('DID manager test stub invalid.');
            }

            return;
        }

        if (class_exists(DidCodec::class)) {
            return;
        }

        if (file_exists($path)) {
            require_once $path;
        }

        if (!class_exists(DidCodec::class)) {
            $logger->error('FAIR DID manager classes are unavailable.');
            $logger->error('Install dependencies with composer install.');
            throw new \RuntimeException('DID manager dependency missing.');
        }
    }
}
