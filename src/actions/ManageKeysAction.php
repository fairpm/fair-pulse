<?php

declare(strict_types=1);

namespace FairPulse\Actions;

use FairPulse\Core\ActionRuntime;
use FairPulse\Services\KeyManagementService;

final class ManageKeysAction
{
    public function __construct(private readonly ActionRuntime $runtime)
    {
    }

    public function run(): int
    {
        $service = new KeyManagementService($this->runtime->env());
        $keys = $service->loadFromEnvironment();

        if ($keys === null) {
            $this->runtime->logger()->error('Cryptographic keys not found in repository secrets.');
            $this->runtime->logger()->error('For security, keys must be generated on your LOCAL machine.');
            $this->runtime->logger()->error('');
            $this->runtime->logger()->error('INSTRUCTIONS:');
            $this->runtime->logger()->error('1. Clone this repository to your local machine');
            $this->runtime->logger()->error('2. Run: composer fair:generate-keys-local');
            $this->runtime->logger()->error('   (or: php src/actions/GenerateKeysLocalAction.php)');
            $this->runtime->logger()->error('3. Copy the generated keys to GitHub Secrets');
            $this->runtime->logger()->error('4. Re-run this workflow');
            $this->runtime->logger()->error('');
            $this->runtime->logger()->error('Keys are never generated in GitHub Actions for security.');

            $this->runtime->output()->write('keys_exist', 'false');
            return 1;
        }

        $this->runtime->logger()->notice('Using existing keys from secrets');
        $this->runtime->output()->write('keys_exist', 'true');
        $this->runtime->output()->write('rotation_private', $keys->rotationPrivate, true);
        $this->runtime->output()->write('rotation_public', $keys->rotationPublic, true);
        $this->runtime->output()->write('verification_private', $keys->verificationPrivate, true);
        $this->runtime->output()->write('verification_public', $keys->verificationPublic, true);

        if ($keys->hasDid()) {
            $this->runtime->output()->write('did', (string) $keys->did);
            $this->runtime->output()->write('did_exists', 'true');
        } else {
            $this->runtime->output()->write('did_exists', 'false');
        }

        return 0;
    }
}

if (PHP_SAPI === 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    require_once __DIR__ . '/../bootstrap.php';

    $action = new ManageKeysAction(new \FairPulse\Core\ActionRuntime());
    exit($action->run());
}
