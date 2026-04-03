<?php

declare(strict_types=1);

namespace FairPulse\Tests;

use FairPulse\Tests\Support\ScriptRunner;
use PHPUnit\Framework\TestCase;

final class ManageKeysScriptTest extends TestCase
{
    public function testFailsWhenKeysAreMissing(): void
    {
        $outputFile = tempnam(sys_get_temp_dir(), 'fair-output-');

        $result = ScriptRunner::run(
            __DIR__ . '/../src/actions/ManageKeysAction.php',
            [
                'GITHUB_OUTPUT' => $outputFile,
            ]
        );

        self::assertSame(1, $result->exitCode);
        self::assertStringContainsString('Cryptographic keys not found', $result->stdout);

        $outputContent = (string) file_get_contents($outputFile);
        self::assertStringContainsString('keys_exist=false', $outputContent);
    }

    public function testOutputsKeysAndDidWhenPresent(): void
    {
        $outputFile = tempnam(sys_get_temp_dir(), 'fair-output-');

        $result = ScriptRunner::run(
            __DIR__ . '/../src/actions/ManageKeysAction.php',
            [
                'GITHUB_OUTPUT' => $outputFile,
                'FAIR_ROTATION_KEY_PRIVATE' => 'rotation-private',
                'FAIR_ROTATION_KEY_PUBLIC' => 'rotation-public',
                'FAIR_VERIFICATION_KEY_PRIVATE' => 'verification-private',
                'FAIR_VERIFICATION_KEY_PUBLIC' => 'verification-public',
                'FAIR_DID' => 'did:plc:example123',
            ]
        );

        self::assertSame(0, $result->exitCode);
        self::assertStringContainsString('Using existing keys from secrets', $result->stdout);

        $outputContent = (string) file_get_contents($outputFile);
        self::assertStringContainsString('keys_exist=true', $outputContent);
        self::assertStringContainsString('did=did:plc:example123', $outputContent);
        self::assertStringContainsString('did_exists=true', $outputContent);
    }
}
