<?php

declare(strict_types=1);

namespace FairPulse\Tests\Integration;

use FairPulse\Tests\Support\GitHubOutputParser;
use FairPulse\Tests\Support\ScriptRunner;
use PHPUnit\Framework\TestCase;

final class DidCreationFlowIntegrationTest extends TestCase
{
    public function testManageKeysOutputsFeedIntoSigningPipeline(): void
    {
        $keysOutputFile = tempnam(sys_get_temp_dir(), 'fair-keys-output-');

        $keysResult = ScriptRunner::run(
            __DIR__ . '/../../src/actions/ManageKeysAction.php',
            [
                'GITHUB_OUTPUT' => $keysOutputFile,
                'FAIR_VERIFICATION_KEY_PRIVATE' => 'verification-private',
                'FAIR_VERIFICATION_KEY_PUBLIC' => 'verification-public',
                'FAIR_DID' => 'did:plc:integration-test',
            ]
        );

        self::assertSame(0, $keysResult->exitCode);

        $keysOutputs = GitHubOutputParser::parseFile($keysOutputFile);
        self::assertSame('true', $keysOutputs['keys_exist'] ?? null);
        self::assertSame('true', $keysOutputs['did_exists'] ?? null);
        self::assertSame('did:plc:integration-test', $keysOutputs['did'] ?? null);
        self::assertSame('verification-private', $keysOutputs['verification_private'] ?? null);
        self::assertSame('verification-public', $keysOutputs['verification_public'] ?? null);
        self::assertArrayNotHasKey('rotation_private', $keysOutputs);
        self::assertArrayNotHasKey('rotation_public', $keysOutputs);
    }
}
