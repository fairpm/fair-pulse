<?php

declare(strict_types=1);

namespace FairPulse\Services;

final class FairMetadataSchemaValidator
{
    private ?array $schema = null;

    public function __construct(private readonly string $schemaPath = __DIR__ . '/../../schemas/fair-package-metadata.schema.json')
    {
    }

    public function validate(array $metadata): void
    {
        $schema = $this->loadSchema();
        $errors = $this->validateValue($metadata, $schema, '$', $schema);

        if ($errors !== []) {
            throw new \RuntimeException(
                'Generated metadata does not conform to FAIR schema: ' . implode('; ', $errors)
            );
        }
    }

    private function loadSchema(): array
    {
        if ($this->schema !== null) {
            return $this->schema;
        }

        $contents = file_get_contents($this->schemaPath);
        if ($contents === false) {
            throw new \RuntimeException('Could not read FAIR metadata schema at ' . $this->schemaPath);
        }

        $decoded = json_decode($contents, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Could not decode FAIR metadata schema at ' . $this->schemaPath);
        }

        $this->schema = $decoded;

        return $this->schema;
    }

    /**
     * @return list<string>
     */
    private function validateValue(mixed $value, array $schema, string $path, array $rootSchema): array
    {
        if (isset($schema['$ref']) && is_string($schema['$ref'])) {
            return $this->validateValue($value, $this->resolveRef($schema['$ref'], $rootSchema), $path, $rootSchema);
        }

        if (isset($schema['anyOf']) && is_array($schema['anyOf'])) {
            foreach ($schema['anyOf'] as $option) {
                if (!is_array($option)) {
                    continue;
                }

                if ($this->validateValue($value, $option, $path, $rootSchema) === []) {
                    return [];
                }
            }

            return [$path . ' does not satisfy any allowed schema option'];
        }

        $errors = [];
        $expectedTypes = $this->normalizeTypes($schema['type'] ?? null);

        if ($expectedTypes !== [] && !$this->matchesAnyType($value, $expectedTypes)) {
            return [$path . ' must be of type ' . implode('|', $expectedTypes)];
        }

        if (array_key_exists('const', $schema) && $value !== $schema['const']) {
            $errors[] = $path . ' must equal ' . var_export($schema['const'], true);
        }

        if (isset($schema['enum']) && is_array($schema['enum']) && !in_array($value, $schema['enum'], true)) {
            $errors[] = $path . ' must be one of the allowed values';
        }

        if (is_string($value)) {
            if (isset($schema['minLength']) && mb_strlen($value) < (int) $schema['minLength']) {
                $errors[] = $path . ' must be at least ' . (int) $schema['minLength'] . ' characters';
            }

            if (isset($schema['pattern']) && is_string($schema['pattern'])) {
                $pattern = '/' . str_replace('/', '\\/', $schema['pattern']) . '/';
                if (@preg_match($pattern, $value) !== 1) {
                    $errors[] = $path . ' does not match the required pattern';
                }
            }

            if (isset($schema['format']) && is_string($schema['format'])) {
                $formatError = $this->validateFormat($value, $schema['format'], $path);
                if ($formatError !== null) {
                    $errors[] = $formatError;
                }
            }
        }

        if (is_array($value) && array_is_list($value)) {
            if (isset($schema['minItems']) && count($value) < (int) $schema['minItems']) {
                $errors[] = $path . ' must contain at least ' . (int) $schema['minItems'] . ' item(s)';
            }

            if (isset($schema['items']) && is_array($schema['items'])) {
                foreach ($value as $index => $item) {
                    $errors = array_merge(
                        $errors,
                        $this->validateValue($item, $schema['items'], $path . '[' . $index . ']', $rootSchema)
                    );
                }
            }
        }

        $isObjectLike = is_object($value) || (is_array($value) && !array_is_list($value));
        if ($isObjectLike) {
            $objectValue = is_object($value) ? get_object_vars($value) : $value;

            if (isset($schema['minProperties']) && count($value) < (int) $schema['minProperties']) {
                $errors[] = $path . ' must contain at least ' . (int) $schema['minProperties'] . ' propert(ies)';
            }

            $properties = isset($schema['properties']) && is_array($schema['properties']) ? $schema['properties'] : [];
            $required = isset($schema['required']) && is_array($schema['required']) ? $schema['required'] : [];

            foreach ($required as $requiredKey) {
                if (is_string($requiredKey) && !array_key_exists($requiredKey, $objectValue)) {
                    $errors[] = $path . '.' . $requiredKey . ' is required';
                }
            }

            if (isset($schema['propertyNames']) && is_array($schema['propertyNames'])) {
                foreach (array_keys($objectValue) as $key) {
                    $errors = array_merge(
                        $errors,
                        $this->validateValue((string) $key, $schema['propertyNames'], $path . ' property name', $rootSchema)
                    );
                }
            }

            foreach ($objectValue as $key => $item) {
                if (isset($properties[$key]) && is_array($properties[$key])) {
                    $errors = array_merge(
                        $errors,
                        $this->validateValue($item, $properties[$key], $path . '.' . $key, $rootSchema)
                    );
                    continue;
                }

                if (($schema['additionalProperties'] ?? true) === false) {
                    $errors[] = $path . '.' . $key . ' is not allowed';
                    continue;
                }

                if (isset($schema['additionalProperties']) && is_array($schema['additionalProperties'])) {
                    $errors = array_merge(
                        $errors,
                        $this->validateValue($item, $schema['additionalProperties'], $path . '.' . $key, $rootSchema)
                    );
                }
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function normalizeTypes(mixed $types): array
    {
        if (is_string($types)) {
            return [$types];
        }

        if (!is_array($types)) {
            return [];
        }

        return array_values(array_filter($types, 'is_string'));
    }

    /**
     * @param list<string> $types
     */
    private function matchesAnyType(mixed $value, array $types): bool
    {
        foreach ($types as $type) {
            if ($this->matchesType($value, $type)) {
                return true;
            }
        }

        return false;
    }

    private function matchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            'object' => is_object($value) || (is_array($value) && !array_is_list($value)),
            'array' => is_array($value) && array_is_list($value),
            'string' => is_string($value),
            'integer' => is_int($value),
            'null' => $value === null,
            'boolean' => is_bool($value),
            default => true,
        };
    }

    private function validateFormat(string $value, string $format, string $path): ?string
    {
        return match ($format) {
            'uri' => filter_var($value, FILTER_VALIDATE_URL) === false ? $path . ' must be a valid URI' : null,
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) === false ? $path . ' must be a valid email address' : null,
            'date-time' => $this->isValidDateTime($value) ? null : $path . ' must be a valid date-time',
            default => null,
        };
    }

    private function isValidDateTime(string $value): bool
    {
        try {
            new \DateTimeImmutable($value);
            return true;
        } catch (\Exception) {
            return false;
        }
    }

    private function resolveRef(string $ref, array $rootSchema): array
    {
        if (!str_starts_with($ref, '#/')) {
            throw new \RuntimeException('Unsupported schema reference: ' . $ref);
        }

        $node = $rootSchema;
        foreach (explode('/', substr($ref, 2)) as $segment) {
            if (!is_array($node) || !array_key_exists($segment, $node)) {
                throw new \RuntimeException('Could not resolve schema reference: ' . $ref);
            }

            $node = $node[$segment];
        }

        if (!is_array($node)) {
            throw new \RuntimeException('Resolved schema reference is not an object: ' . $ref);
        }

        return $node;
    }
}