<?php

declare(strict_types=1);

namespace FairPulse\Tests;

use FairPulse\Tests\Support\ScriptRunner;
use PHPUnit\Framework\TestCase;

final class CreateDidScriptTest extends TestCase
{
    public function testUsesExistingDidWhenProvided(): void
    {
        $outputFile = tempnam(sys_get_temp_dir(), 'fair-output-');

        $result = ScriptRunner::run(
            __DIR__ . '/../scripts/fair/create-did.php',
            [
                'GITHUB_OUTPUT' => $outputFile,
                'ROTATION_PRIVATE' => 'rotation-private',
                'ROTATION_PUBLIC' => 'rotation-public',
                'VERIFICATION_PRIVATE' => 'verification-private',
                'VERIFICATION_PUBLIC' => 'verification-public',
                'EXISTING_DID' => 'did:plc:already-set',
                'DID_EXISTS' => 'true',
                'REPO_URL' => 'https://github.com/fairpm/fair-pulse',
            ]
        );

        self::assertSame(0, $result->exitCode);
        self::assertStringContainsString('Using existing DID: did:plc:already-set', $result->stdout);

        $outputContent = (string) file_get_contents($outputFile);
        self::assertStringContainsString('did=did:plc:already-set', $outputContent);
        self::assertStringContainsString('created=false', $outputContent);
    }
}
