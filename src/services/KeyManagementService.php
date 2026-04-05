<?php

declare(strict_types=1);

namespace FairPulse\Services;

use FairPulse\Core\Env;
use FairPulse\Models\KeySet;

final class KeyManagementService
{
    public function __construct(private readonly Env $env)
    {
    }

    public function loadFromEnvironment(): ?KeySet
    {
        $verificationPrivate = $this->env->get('FAIR_VERIFICATION_KEY_PRIVATE');
        $verificationPublic = $this->env->get('FAIR_VERIFICATION_KEY_PUBLIC');
        $did = $this->env->get('FAIR_DID');

        $verificationKeysExist = $verificationPrivate !== null && $verificationPrivate !== ''
            && $verificationPublic !== null && $verificationPublic !== '';

        if (!$verificationKeysExist) {
            return null;
        }

        return new KeySet(
            $verificationPrivate,
            $verificationPublic,
            $did,
        );
    }
}
