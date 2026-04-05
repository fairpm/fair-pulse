<?php

declare(strict_types=1);

namespace FairPulse\Models;

final class KeySet
{
    public function __construct(
        public readonly string $verificationPrivate,
        public readonly string $verificationPublic,
        public readonly ?string $did,
    ) {
    }

    public function hasDid(): bool
    {
        return $this->did !== null && $this->did !== '';
    }
}
