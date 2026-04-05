<?php

declare(strict_types=1);

namespace FairPulse\Actions;

use FAIR\DID\Crypto\DidCodec;
use FAIR\DID\PLC\PlcClient;
use FairPulse\Core\ActionRuntime;
use FairPulse\Core\DidManagerBootstrap;
use FairPulse\Services\DidServiceUpdateService;

final class CreateDidLocalAction
{
    public function __construct(private readonly ActionRuntime $runtime)
    {
    }

    public function run(): int
    {
        $this->runtime->logger()->raw("\n");
        $this->runtime->logger()->raw("╔════════════════════════════════════════════════════════════╗\n");
        $this->runtime->logger()->raw("║  FAIR Local Setup                                         ║\n");
        $this->runtime->logger()->raw("║  Keys + DID + Service Endpoint — all on YOUR machine      ║\n");
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

        $repoUrl = $this->resolveRepoUrl();
        if ($repoUrl === null) {
            return 1;
        }

        try {
            DidManagerBootstrap::requireAutoload($this->runtime->logger());

            $this->runtime->logger()->raw("🔐 Generating cryptographic keys...\n\n");

            $rotationKey = DidCodec::generate_key_pair();
            $verificationKey = DidCodec::generate_ed25519_key_pair();

            $rotationPrivate = $rotationKey->encode_private();
            $rotationPublic = $rotationKey->encode_public();
            $verificationPrivate = $verificationKey->encode_private();
            $verificationPublic = $verificationKey->encode_public();

            $this->runtime->logger()->raw("Keys generated successfully!\n\n");

            $this->runtime->logger()->raw("🆔 Creating DID on PLC directory...\n\n");

            $handle = basename($repoUrl);
            $operation = DidCodec::create_plc_operation($rotationKey, $verificationKey, $handle);
            $signedOperation = DidCodec::sign_plc_operation($operation, $rotationKey);
            $did = DidCodec::generate_plc_did($signedOperation);
            $cid = $signedOperation->get_cid();

            $this->runtime->logger()->raw("DID generated: {$did}\n");
            $this->runtime->logger()->raw("Operation CID: {$cid}\n\n");

            $client = new PlcClient();
            try {
                $client->create_did($did, (array) $signedOperation->jsonSerialize());
                $this->runtime->logger()->raw("DID submitted to PLC directory.\n\n");
            } catch (\Exception $exception) {
                $this->runtime->logger()->raw("WARNING: Could not submit to PLC directory: {$exception->getMessage()}\n");
                $this->runtime->logger()->raw("   DID can still be used locally.\n\n");
            }

            $metadataUrl = rtrim($repoUrl, '/') . '/releases/latest/download/fair-metadata.json';
            $this->runtime->logger()->raw("🔗 Setting DID service endpoint to:\n");
            $this->runtime->logger()->raw("   {$metadataUrl}\n\n");

            try {
                $updateService = new DidServiceUpdateService($this->runtime->logger());
                $updateService->update($did, $rotationPrivate, $metadataUrl, $cid);
                $this->runtime->logger()->raw("\nService endpoint set successfully!\n\n");
            } catch (\Exception $exception) {
                $this->runtime->logger()->raw("WARNING: Could not set service endpoint: {$exception->getMessage()}\n");
                $this->runtime->logger()->raw("   You can retry later with: composer fair:update-service-local\n\n");
            }

            $this->runtime->logger()->raw("╔════════════════════════════════════════════════════════════╗\n");
            $this->runtime->logger()->raw("║  📋 ADD THESE TO YOUR GITHUB REPOSITORY                    ║\n");
            $this->runtime->logger()->raw("╚════════════════════════════════════════════════════════════╝\n\n");
            $this->runtime->logger()->raw("Go to your repository: {$repoUrl}\n");
            $this->runtime->logger()->raw("Navigate to: Settings → Secrets and variables → Actions\n\n");

            $this->runtime->logger()->raw("── SECRETS (Secrets tab → New repository secret) ──────────\n\n");
            $this->renderKey('FAIR_VERIFICATION_KEY_PRIVATE', $verificationPrivate);
            $this->renderKey('FAIR_VERIFICATION_KEY_PUBLIC', $verificationPublic);

            $this->runtime->logger()->raw("── VARIABLE (Variables tab → New repository variable) ─────\n\n");
            $this->renderKey('FAIR_DID', $did);

            $this->runtime->logger()->raw("╔════════════════════════════════════════════════════════════╗\n");
            $this->runtime->logger()->raw("║  🔒 BACK UP THESE ROTATION KEYS SECURELY                  ║\n");
            $this->runtime->logger()->raw("╚════════════════════════════════════════════════════════════╝\n\n");
            $this->runtime->logger()->raw("These keys stay on YOUR machine. Do NOT add them to GitHub.\n");
            $this->runtime->logger()->raw("Store them in a password manager or encrypted backup.\n");
            $this->runtime->logger()->raw("You only need them if you ever change the DID document.\n\n");
            $this->renderKey('FAIR_ROTATION_KEY_PRIVATE', $rotationPrivate);
            $this->renderKey('FAIR_ROTATION_KEY_PUBLIC', $rotationPublic);

            $this->runtime->logger()->raw("╔════════════════════════════════════════════════════════════╗\n");
            $this->runtime->logger()->raw("║  ✅ SETUP COMPLETE                                         ║\n");
            $this->runtime->logger()->raw("╚════════════════════════════════════════════════════════════╝\n\n");
            $this->runtime->logger()->raw("Your DID: {$did}\n");
            $this->runtime->logger()->raw("Service endpoint: {$metadataUrl}\n\n");
            $this->runtime->logger()->raw("The endpoint uses GitHub's /releases/latest/download/ URL\n");
            $this->runtime->logger()->raw("which always resolves to the most recent release.\n");
            $this->runtime->logger()->raw("No DID updates are needed when you publish new releases.\n\n");
            $this->runtime->logger()->raw("Your CI workflow only needs verification keys + DID.\n");
            $this->runtime->logger()->raw("Rotation keys never leave your machine.\n\n");

            return 0;
        } catch (\RuntimeException $exception) {
            $this->runtime->logger()->raw("ERROR: {$exception->getMessage()}\n\n");
            return 1;
        } catch (\Throwable $exception) {
            $this->runtime->logger()->raw("ERROR: Setup failed.\n");
            $this->runtime->logger()->raw("   {$exception->getMessage()}\n\n");
            return 1;
        }
    }

    private function resolveRepoUrl(): ?string
    {
        global $argv;
        $args = array_slice($argv ?? [], 1);

        // Accept -- separator used by composer scripts: composer fair:setup-local -- https://...
        $args = array_values(array_filter($args, static fn(string $arg) => $arg !== '--'));

        $url = $args[0] ?? null;

        if ($url === null || $url === '') {
            $this->runtime->logger()->raw("No repository URL provided.\n\n");

            if ($this->isInteractiveTerminal()) {
                $this->runtime->logger()->raw("Enter your GitHub repository URL (e.g. https://github.com/your-name/your-plugin): ");
                $input = trim((string) fgets(STDIN));

                if ($input === '') {
                    $this->runtime->logger()->raw("\nERROR: No URL entered. Cannot continue without a repository URL.\n");
                    $this->runtime->logger()->raw("   Re-run with: composer fair:setup-local -- https://github.com/<owner>/<repo>\n\n");
                    return null;
                }

                $url = $input;
            } else {
                $this->runtime->logger()->raw("Usage: composer fair:setup-local -- <repository-url>\n\n");
                $this->runtime->logger()->raw("Example:\n");
                $this->runtime->logger()->raw("  composer fair:setup-local -- https://github.com/your-name/your-plugin\n\n");
                return null;
            }
        }

        if (!preg_match('#^https://github\.com/[A-Za-z0-9._-]+/[A-Za-z0-9._-]+$#', rtrim($url, '/'))) {
            $this->runtime->logger()->raw("\nERROR: Invalid repository URL.\n");
            $this->runtime->logger()->raw("   Expected format: https://github.com/<owner>/<repo>\n");
            $this->runtime->logger()->raw("   Got: {$url}\n\n");
            return null;
        }

        return rtrim($url, '/');
    }

    private function isInteractiveTerminal(): bool
    {
        return defined('STDIN') && function_exists('posix_isatty') && posix_isatty(STDIN);
    }

    private function renderKey(string $name, string $value): void
    {
        $this->runtime->logger()->raw("  Name:  {$name}\n");
        $this->runtime->logger()->raw("  Value: {$value}\n\n");
    }
}

if (PHP_SAPI === 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    require_once __DIR__ . '/../bootstrap.php';

    $action = new CreateDidLocalAction(new \FairPulse\Core\ActionRuntime());
    exit($action->run());
}
