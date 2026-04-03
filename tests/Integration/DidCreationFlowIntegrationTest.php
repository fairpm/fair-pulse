<?php

declare(strict_types=1);

namespace FairPulse\Tests\Integration;

use FairPulse\Tests\Support\GitHubOutputParser;
use FairPulse\Tests\Support\ScriptRunner;
use PHPUnit\Framework\TestCase;

final class DidCreationFlowIntegrationTest extends TestCase
{
    public function testManageKeysAndCreateDidActionsWorkTogether(): void
    {
        $keysOutputFile = tempnam(sys_get_temp_dir(), 'fair-keys-output-');

        $keysResult = ScriptRunner::run(
            __DIR__ . '/../../src/actions/ManageKeysAction.php',
            [
                'GITHUB_OUTPUT' => $keysOutputFile,
                'FAIR_ROTATION_KEY_PRIVATE' => 'rotation-private',
                'FAIR_ROTATION_KEY_PUBLIC' => 'rotation-public',
                'FAIR_VERIFICATION_KEY_PRIVATE' => 'verification-private',
                'FAIR_VERIFICATION_KEY_PUBLIC' => 'verification-public',
            ]
        );

        self::assertSame(0, $keysResult->exitCode);

        $keysOutputs = GitHubOutputParser::parseFile($keysOutputFile);
        self::assertSame('true', $keysOutputs['keys_exist'] ?? null);
        self::assertSame('false', $keysOutputs['did_exists'] ?? null);

        $didOutputFile = tempnam(sys_get_temp_dir(), 'fair-did-output-');
        $didResult = ScriptRunner::run(
            __DIR__ . '/../../src/actions/CreateDidAction.php',
            [
                'GITHUB_OUTPUT' => $didOutputFile,
                'ROTATION_PRIVATE' => $keysOutputs['rotation_private'] ?? '',
                'ROTATION_PUBLIC' => $keysOutputs['rotation_public'] ?? '',
                'VERIFICATION_PRIVATE' => $keysOutputs['verification_private'] ?? '',
                'VERIFICATION_PUBLIC' => $keysOutputs['verification_public'] ?? '',
                'DID_EXISTS' => $keysOutputs['did_exists'] ?? 'false',
                'EXISTING_DID' => $keysOutputs['did'] ?? '',
                'REPO_URL' => 'https://github.com/fairpm/fair-pulse',
            ]
        );

        self::assertSame(0, $didResult->exitCode);
        $didOutputs = GitHubOutputParser::parseFile($didOutputFile);

        self::assertArrayHasKey('did', $didOutputs);
        self::assertSame('true', $didOutputs['created'] ?? null);
        self::assertArrayHasKey('cid', $didOutputs);
        self::assertStringStartsWith('did:plc:', $didOutputs['did']);
    }
}
