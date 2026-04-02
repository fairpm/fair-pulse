<?php

declare(strict_types=1);

namespace FairPulse\Models;

final class DidCreationResult
{
    public function __construct(
        public readonly string $did,
        public readonly bool $created,
        public readonly ?string $cid,
    ) {
    }
}
