<?php

declare(strict_types=1);

namespace FairPulse\Tests\Support;

final class ScriptResult
{
    public function __construct(
        public readonly int $exitCode,
        public readonly string $stdout,
        public readonly string $stderr,
    ) {
    }
}

final class ScriptRunner
{
    public static function run(string $scriptPath, array $env = []): ScriptResult
    {
        $command = ['php', $scriptPath];
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            $command,
            $descriptors,
            $pipes,
            dirname(__DIR__, 2),
            array_merge($_ENV, $env),
        );

        if (!is_resource($process)) {
            throw new \RuntimeException('Failed to start subprocess for script test.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return new ScriptResult($exitCode, (string) $stdout, (string) $stderr);
    }
}
