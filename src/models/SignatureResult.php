<?php

declare(strict_types=1);

namespace FairPulse\Models;

final class SignatureResult
{
    public function __construct(
        public readonly string $signature,
        public readonly string $checksum,
    ) {
    }
}
