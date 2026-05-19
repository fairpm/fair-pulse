<?php

declare(strict_types=1);

namespace FairPulse\Tests;

use FairPulse\Tests\Support\ScriptRunner;
use PHPUnit\Framework\TestCase;

final class GenerateMetadataScriptTest extends TestCase
{
    public function testGeneratesFairMetadataWithReleaseArtifact(): void
    {
        $workspaceDir = sys_get_temp_dir() . '/fair-workspace-' . bin2hex(random_bytes(5));
        mkdir($workspaceDir, 0777, true);

        $pluginMainFile = $workspaceDir . '/example-plugin.php';
        file_put_contents(
            $pluginMainFile,
            "<?php\n/*\nPlugin Name: Example Plugin\nRequires PHP: 8.1\nRequires at least: 6.5\n*/\n"
        );

        file_put_contents($workspaceDir . '/readme.txt', "Example plugin readme.\n");

        $artifactPath = tempnam(sys_get_temp_dir(), 'fair-artifact-');
        file_put_contents($artifactPath, 'artifact-content');

        $outputFile = tempnam(sys_get_temp_dir(), 'fair-output-');

        $result = ScriptRunner::run(
            __DIR__ . '/../src/actions/GenerateMetadataAction.php',
            [
                'GITHUB_OUTPUT' => $outputFile,
                'GITHUB_WORKSPACE' => $workspaceDir,
                'DID' => 'did:plc:metadata123',
                'VERSION' => 'v1.2.3',
                'CHECKSUM' => 'sha256:' . str_repeat('a', 64),
                'SIGNATURE' => 'signature123',
                'ARTIFACT_PATH' => $artifactPath,
                'VERIFICATION_PUBLIC' => 'verification-public',
                'REPO_URL' => 'https://github.com/fairpm/fair-pulse',
            ]
        );

        self::assertSame(0, $result->exitCode);
        self::assertStringContainsString('FAIR metadata generated successfully', $result->stdout);

        $outputContent = (string) file_get_contents($outputFile);
        self::assertStringContainsString('metadata_path=/tmp/fair-metadata.json', $outputContent);

        $metadata = json_decode((string) file_get_contents('/tmp/fair-metadata.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('did:plc:metadata123', $metadata['id']);
        self::assertSame('https://fair.pm/ns/metadata/v1', $metadata['@context']);
        self::assertSame('wp-plugin', $metadata['type']);
        self::assertSame('example-plugin/example-plugin.php', $metadata['filename']);
        self::assertSame('1.2.3', $metadata['releases'][0]['version']);
        self::assertSame('signature123', $metadata['releases'][0]['artifacts']['package'][0]['signature']);
        self::assertSame('sha256:' . str_repeat('a', 64), $metadata['releases'][0]['artifacts']['package'][0]['checksum']);
        self::assertSame([], $metadata['releases'][0]['suggests']);
        self::assertSame([], $metadata['releases'][0]['provides']);
        self::assertArrayNotHasKey('generatedAt', $metadata);
        self::assertArrayNotHasKey('tags', $metadata);
    }

    public function testGenerationFailsWhenSchemaRequirementsAreMissing(): void
    {
        $workspaceDir = sys_get_temp_dir() . '/fair-workspace-' . bin2hex(random_bytes(5));
        mkdir($workspaceDir, 0777, true);

        file_put_contents(
            $workspaceDir . '/example-plugin.php',
            "<?php\n/*\nPlugin Name: Example Plugin\nRequires PHP: 8.1\nRequires at least: 6.5\n*/\n"
        );

        file_put_contents($workspaceDir . '/readme.txt', "Example plugin readme.\n");

        $artifactPath = tempnam(sys_get_temp_dir(), 'fair-artifact-');
        file_put_contents($artifactPath, 'artifact-content');

        $outputFile = tempnam(sys_get_temp_dir(), 'fair-output-');

        $result = ScriptRunner::run(
            __DIR__ . '/../src/actions/GenerateMetadataAction.php',
            [
                'GITHUB_OUTPUT' => $outputFile,
                'GITHUB_WORKSPACE' => $workspaceDir,
                'DID' => 'did:plc:metadata123',
                'VERSION' => 'v1.2.3',
                'CHECKSUM' => '',
                'SIGNATURE' => '',
                'ARTIFACT_PATH' => $artifactPath,
                'VERIFICATION_PUBLIC' => 'verification-public',
                'REPO_URL' => 'https://github.com/fairpm/fair-pulse',
            ]
        );

        self::assertSame(1, $result->exitCode);
        self::assertStringContainsString('Generated metadata does not conform to FAIR schema', $result->stdout);
    }
}
