<?php

declare(strict_types=1);

namespace FairPulse\Services;

use FairPulse\Interfaces\LoggerInterface;

final class LocalDidManagerInstallerService
{
    private const DID_MANAGER_PATH = '/tmp/did-manager';

    private const DID_MANAGER_AUTOLOAD = '/tmp/did-manager/vendor/autoload.php';

    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function ensureInstalled(): string
    {
        if (file_exists(self::DID_MANAGER_AUTOLOAD)) {
            return self::DID_MANAGER_AUTOLOAD;
        }

        $this->logger->warning('FAIR DID Manager not found locally.');
        $this->logger->raw("   Cloning from GitHub...\n\n");

        $cloneCmd = sprintf(
            'git clone --branch initial-implementation --depth 1 https://github.com/fairpm/did-manager.git %s 2>&1',
            escapeshellarg(self::DID_MANAGER_PATH)
        );
        exec($cloneCmd, $output, $returnCode);
        if ($returnCode !== 0) {
            throw new \RuntimeException('Failed to clone DID Manager. Please install it manually or check your internet connection.');
        }

        $this->logger->raw("   Installing dependencies...\n");
        exec('cd /tmp/did-manager && composer install --no-dev --prefer-dist --no-progress 2>&1', $output, $returnCode);
        if ($returnCode !== 0) {
            throw new \RuntimeException('Failed to install DID Manager dependencies.');
        }

        return self::DID_MANAGER_AUTOLOAD;
    }
}
