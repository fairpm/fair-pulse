<?php

declare(strict_types=1);

namespace FairPulse\Actions;

use FairPulse\Core\ActionRuntime;
use FairPulse\Core\DidManagerBootstrap;
use FairPulse\Services\DidServiceUpdateService;

final class UpdateDidServiceAction
{
    public function __construct(private readonly ActionRuntime $runtime)
    {
    }

    public function run(): int
    {
        $this->runtime->logger()->raw("\n");
        $this->runtime->logger()->raw("╔════════════════════════════════════════════════════════════╗\n");
        $this->runtime->logger()->raw("║  FAIR DID Service Endpoint Update (Local)                 ║\n");
        $this->runtime->logger()->raw("║  Updates which URL your DID points to for metadata        ║\n");
        $this->runtime->logger()->raw("╚════════════════════════════════════════════════════════════╝\n\n");

        if ($this->runtime->env()->get('GITHUB_ACTIONS') === 'true') {
            $this->runtime->logger()->raw("ERROR: This script should NOT be run in GitHub Actions!\n");
            $this->runtime->logger()->raw("   Run it locally on your machine instead.\n\n");
            return 1;
        }

        if (!class_exists(\FAIR\DID\Crypto\DidCodec::class)) {
            $this->runtime->logger()->raw("ERROR: FAIR DID manager dependency is missing.\n");
            $this->runtime->logger()->raw("   Run: composer install\n\n");
            return 1;
        }

        try {
            DidManagerBootstrap::requireAutoload($this->runtime->logger());

            $did = $this->resolveInput('DID', 'Your DID (e.g. did:plc:abc123)');
            if ($did === null) return 1;

            $rotationPrivate = $this->resolveInput('ROTATION_PRIVATE', 'Rotation private key');
            if ($rotationPrivate === null) return 1;

            $metadataUrl = $this->resolveInput('METADATA_URL', 'Metadata URL (e.g. https://github.com/<owner>/<repo>/releases/latest/download/fair-metadata.json)');
            if ($metadataUrl === null) return 1;

            if (!filter_var($metadataUrl, FILTER_VALIDATE_URL)) {
                $this->runtime->logger()->raw("\nERROR: Invalid metadata URL: {$metadataUrl}\n");
                $this->runtime->logger()->raw("   Expected a valid https:// URL.\n\n");
                return 1;
            }

            $prevCid = $this->runtime->env()->get('PREV_CID');
            if ($prevCid === null || $prevCid === '') {
                $prevCid = $this->promptOptional('Previous operation CID (optional, press Enter to skip)');
            }

            $this->runtime->logger()->raw("\n");
            $this->runtime->logger()->raw("DID:          {$did}\n");
            $this->runtime->logger()->raw("Metadata URL: {$metadataUrl}\n");
            $this->runtime->logger()->raw("Prev CID:     " . ($prevCid !== '' ? $prevCid : '(will fetch from PLC)') . "\n\n");

            $service = new DidServiceUpdateService($this->runtime->logger());
            $service->update($did, $rotationPrivate, $metadataUrl, $prevCid !== '' ? $prevCid : null);

            $this->runtime->logger()->raw("\n✅ DID service endpoint updated successfully.\n\n");
            return 0;
        } catch (\InvalidArgumentException $exception) {
            $this->runtime->logger()->raw("\nERROR: Invalid input — " . $exception->getMessage() . "\n\n");
            return 1;
        } catch (\RuntimeException $exception) {
            $this->runtime->logger()->raw("\nERROR: " . $exception->getMessage() . "\n\n");
            return 1;
        } catch (\Throwable $exception) {
            $this->runtime->logger()->raw("\nERROR: Could not update DID service endpoint.\n");
            $this->runtime->logger()->raw("   " . $exception->getMessage() . "\n");
            $this->runtime->logger()->raw("   " . get_class($exception) . ' in ' . $exception->getFile() . ':' . $exception->getLine() . "\n\n");
            return 1;
        }
    }

    private function resolveInput(string $envVar, string $label): ?string
    {
        $value = $this->runtime->env()->get($envVar);

        if ($value !== null && $value !== '') {
            return $value;
        }

        if ($this->isInteractiveTerminal()) {
            $this->runtime->logger()->raw("{$label}: ");
            $input = trim((string) fgets(STDIN));

            if ($input === '') {
                $this->runtime->logger()->raw("\nERROR: {$label} is required and cannot be empty.\n\n");
                return null;
            }

            return $input;
        }

        $this->runtime->logger()->raw("ERROR: {$envVar} is required but not set.\n");
        $this->runtime->logger()->raw("   Set it as an environment variable or run interactively to be prompted.\n\n");
        return null;
    }

    private function promptOptional(string $label): string
    {
        if (!$this->isInteractiveTerminal()) {
            return '';
        }

        $this->runtime->logger()->raw("{$label}: ");
        return trim((string) fgets(STDIN));
    }

    private function isInteractiveTerminal(): bool
    {
        return defined('STDIN') && function_exists('posix_isatty') && posix_isatty(STDIN);
    }
}

if (PHP_SAPI === 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    require_once __DIR__ . '/../bootstrap.php';

    $action = new UpdateDidServiceAction(new \FairPulse\Core\ActionRuntime());
    exit($action->run());
}
