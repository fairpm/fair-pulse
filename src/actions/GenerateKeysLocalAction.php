<?php

declare(strict_types=1);

namespace FairPulse\Actions;

use FAIR\DID\Crypto\DidCodec;
use FairPulse\Core\ActionRuntime;

final class GenerateKeysLocalAction
{
    public function __construct(private readonly ActionRuntime $runtime)
    {
    }

    public function run(): int
    {
        $this->runtime->logger()->raw("\n");
        $this->runtime->logger()->raw("╔════════════════════════════════════════════════════════════╗\n");
        $this->runtime->logger()->raw("║  FAIR Cryptographic Key Generator (Local)                 ║\n");
        $this->runtime->logger()->raw("║  Keys are generated on YOUR machine for security          ║\n");
        $this->runtime->logger()->raw("╚════════════════════════════════════════════════════════════╝\n\n");

        if ($this->runtime->env()->get('GITHUB_ACTIONS') === 'true') {
            $this->runtime->logger()->raw("ERROR: This script should NOT be run in GitHub Actions!\n");
            $this->runtime->logger()->raw("   Run it locally on your machine instead.\n\n");
            return 1;
        }

        if (!class_exists(DidCodec::class)) {
            $this->runtime->logger()->raw("ERROR: FAIR DID manager dependency is missing.\n");
            $this->runtime->logger()->raw("   Run: composer install\n\n");
            return 1;
        }

        try {
            $this->runtime->logger()->raw("🔐 Generating cryptographic keys...\n\n");

            $rotationKey = DidCodec::generate_key_pair();
            $verificationKey = DidCodec::generate_ed25519_key_pair();

            $rotationPrivate = $rotationKey->encode_private();
            $rotationPublic = $rotationKey->encode_public();
            $verificationPrivate = $verificationKey->encode_private();
            $verificationPublic = $verificationKey->encode_public();

            $this->runtime->logger()->raw("Keys generated successfully!\n\n");
            $this->runtime->logger()->raw("╔════════════════════════════════════════════════════════════╗\n");
            $this->runtime->logger()->raw("║  📋 COPY THESE KEYS TO YOUR GITHUB REPOSITORY SECRETS     ║\n");
            $this->runtime->logger()->raw("╚════════════════════════════════════════════════════════════╝\n\n");
            $this->runtime->logger()->raw("1. Go to your repository on GitHub\n");
            $this->runtime->logger()->raw("2. Navigate to: Settings → Secrets and variables → Actions\n");
            $this->runtime->logger()->raw("3. Click 'New repository secret' for each key below:\n\n");

            $this->renderKey('FAIR_ROTATION_KEY_PRIVATE', $rotationPrivate);
            $this->renderKey('FAIR_ROTATION_KEY_PUBLIC', $rotationPublic);
            $this->renderKey('FAIR_VERIFICATION_KEY_PRIVATE', $verificationPrivate);
            $this->renderKey('FAIR_VERIFICATION_KEY_PUBLIC', $verificationPublic);

            $this->runtime->logger()->raw("╔════════════════════════════════════════════════════════════╗\n");
            $this->runtime->logger()->raw("║  IMPORTANT SECURITY NOTES                                  ║\n");
            $this->runtime->logger()->raw("╚════════════════════════════════════════════════════════════╝\n\n");
            $this->runtime->logger()->raw("• These keys were generated on YOUR local machine\n");
            $this->runtime->logger()->raw("• They were NEVER uploaded to GitHub or any server\n");
            $this->runtime->logger()->raw("• Keep the PRIVATE keys secure - never share them\n");
            $this->runtime->logger()->raw("• After copying to GitHub Secrets, clear your terminal history\n");
            $this->runtime->logger()->raw("• Use composer.lock to keep crypto dependencies pinned\n\n");
            $this->runtime->logger()->raw("Once all secrets are added, run the workflow on GitHub!\n\n");

            return 0;
        } catch (\RuntimeException $exception) {
            $this->runtime->logger()->raw("ERROR: {$exception->getMessage()}\n\n");
            return 1;
        } catch (\Throwable $exception) {
            $this->runtime->logger()->raw("ERROR: Failed to generate keys.\n");
            $this->runtime->logger()->raw("   {$exception->getMessage()}\n\n");
            return 1;
        }
    }

    private function renderKey(string $secretName, string $secretValue): void
    {
        $this->runtime->logger()->raw("─────────────────────────────────────────────────────────────\n");
        $this->runtime->logger()->raw("SECRET NAME: {$secretName}\n");
        $this->runtime->logger()->raw("─────────────────────────────────────────────────────────────\n");
        $this->runtime->logger()->raw($secretValue . "\n\n");
    }
}

if (PHP_SAPI === 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    require_once __DIR__ . '/../bootstrap.php';

    $action = new GenerateKeysLocalAction(new \FairPulse\Core\ActionRuntime());
    exit($action->run());
}
