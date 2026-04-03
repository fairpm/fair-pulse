<?php

declare(strict_types=1);

namespace FairPulse\Core;

final class Env
{
    public function get(string $name, ?string $default = null): ?string
    {
        $value = getenv($name);
        if ($value === false || $value === null) {
            return $default;
        }

        return $value;
    }

    public function getRequired(string $name, string $errorMessage): string
    {
        $value = $this->get($name);
        if ($value === null || $value === '') {
            throw new \RuntimeException($errorMessage);
        }

        return $value;
    }

    public function getBool(string $name): bool
    {
        return $this->get($name) === 'true';
    }
}
