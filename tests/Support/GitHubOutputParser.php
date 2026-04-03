<?php

declare(strict_types=1);

namespace FairPulse\Tests\Support;

final class GitHubOutputParser
{
    /**
     * @return array<string, string>
     */
    public static function parseFile(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return [];
        }

        $outputs = [];
        $lineCount = count($lines);

        for ($i = 0; $i < $lineCount; $i++) {
            $line = $lines[$i];

            if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)<<(.+)$/', $line, $matches) === 1) {
                $key = $matches[1];
                $delimiter = $matches[2];
                $buffer = [];
                $i++;
                while ($i < $lineCount && $lines[$i] !== $delimiter) {
                    $buffer[] = $lines[$i];
                    $i++;
                }
                $outputs[$key] = implode("\n", $buffer);
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $outputs[$parts[0]] = $parts[1];
            }
        }

        return $outputs;
    }
}
