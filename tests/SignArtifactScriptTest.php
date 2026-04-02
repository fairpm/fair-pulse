<?php

declare(strict_types=1);

namespace FairPulse\Tests;

use FairPulse\Tests\Support\ScriptRunner;
use PHPUnit\Framework\TestCase;

final class SignArtifactScriptTest extends TestCase
{
    public function testSignsArtifactAndProducesChecksumOutput(): void
    {
        $artifactPath = tempnam(sys_get_temp_dir(), 'fair-artifact-');
        file_put_contents($artifactPath, 'artifact-bytes-for-signing');

        $outputFile = tempnam(sys_get_temp_dir(), 'fair-output-');

        $result = ScriptRunner::run(
            __DIR__ . '/../src/actions/SignArtifactAction.php',
            [
                'GITHUB_OUTPUT' => $outputFile,
                'VERIFICATION_PRIVATE' => 'verification-private',
                'VERIFICATION_PUBLIC' => 'verification-public',
                'ARTIFACT_PATH' => $artifactPath,
            ]
        );

        self::assertSame(0, $result->exitCode);
        self::assertStringContainsString('Package signed successfully', $result->stdout);

        $outputContent = (string) file_get_contents($outputFile);
        self::assertMatchesRegularExpression('/signature=[A-Za-z0-9_-]+/', $outputContent);
        self::assertMatchesRegularExpression('/checksum=sha256:[a-f0-9]{64}/', $outputContent);
    }
}
