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
        $rotationPrivate = $this->env->get('FAIR_ROTATION_KEY_PRIVATE');
        $rotationPublic = $this->env->get('FAIR_ROTATION_KEY_PUBLIC');
        $verificationPrivate = $this->env->get('FAIR_VERIFICATION_KEY_PRIVATE');
        $verificationPublic = $this->env->get('FAIR_VERIFICATION_KEY_PUBLIC');
        $did = $this->env->get('FAIR_DID');

        $keysExist = $rotationPrivate !== null && $rotationPrivate !== ''
            && $rotationPublic !== null && $rotationPublic !== ''
            && $verificationPrivate !== null && $verificationPrivate !== ''
            && $verificationPublic !== null && $verificationPublic !== '';

        if (!$keysExist) {
            return null;
        }

        return new KeySet(
            $rotationPrivate,
            $rotationPublic,
            $verificationPrivate,
            $verificationPublic,
            $did,
        );
    }
}
