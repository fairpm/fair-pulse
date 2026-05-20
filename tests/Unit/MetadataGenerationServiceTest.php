<?php

declare(strict_types=1);

namespace FairPulse\Tests\Unit;

use FairPulse\Services\MetadataGenerationService;
use PHPUnit\Framework\TestCase;

final class MetadataGenerationServiceTest extends TestCase
{
    public function testGenerateUsesPluginDirectoryNameForSlug(): void
    {
        $baseTempDir = sys_get_temp_dir() . '/fair-workspace-' . bin2hex(random_bytes(5));
        $workspaceDir = $baseTempDir . '/plugin-directory-slug';
        mkdir($workspaceDir, 0777, true);

        file_put_contents(
            $workspaceDir . '/entrypoint.php',
            "<?php\n/*\nPlugin Name: Example Plugin\nRequires PHP: 8.1\nRequires at least: 6.5\n*/\n"
        );

        file_put_contents(
            $workspaceDir . '/readme.txt',
            "=== Example Plugin ===\n"
            . "Requires at least: 6.5\n"
            . "Requires PHP: 8.1\n"
            . "Stable tag: 1.2.3\n\n"
            . "Example plugin short description.\n"
        );

        $artifactPath = tempnam(sys_get_temp_dir(), 'fair-artifact-');
        file_put_contents($artifactPath, 'artifact-content');

        $service = new MetadataGenerationService();
        $metadata = $service->generate(
            'did:plc:metadata123',
            'v1.2.3',
            'sha256:' . str_repeat('a', 64),
            'signature123',
            $artifactPath,
            'https://github.com/fairpm/fair-pulse',
            $workspaceDir,
        );

        self::assertSame('plugin-directory-slug', $metadata['slug'] ?? null);
        self::assertSame('plugin-directory-slug/entrypoint.php', $metadata['filename'] ?? null);
    }

    public function testGenerateDeduplicatesContributorThatMatchesPrimaryAuthor(): void
    {
        $workspaceDir = sys_get_temp_dir() . '/fair-workspace-' . bin2hex(random_bytes(5));
        mkdir($workspaceDir, 0777, true);

        file_put_contents(
            $workspaceDir . '/example-plugin.php',
            "<?php\n/*\nPlugin Name: Example Plugin\nAuthor: johndoe\nAuthor URI: https://example.com\nRequires PHP: 8.1\nRequires at least: 6.5\n*/\n"
        );

        file_put_contents(
            $workspaceDir . '/readme.txt',
            "=== Example Plugin ===\n"
            . "Contributors: johndoe\n"
            . "Requires at least: 6.5\n"
            . "Requires PHP: 8.1\n"
            . "Stable tag: 1.2.3\n"
            . "License: GPL-2.0-or-later\n\n"
            . "Example plugin short description.\n"
        );

        $artifactPath = tempnam(sys_get_temp_dir(), 'fair-artifact-');
        file_put_contents($artifactPath, 'artifact-content');

        $service = new MetadataGenerationService();
        $metadata = $service->generate(
            'did:plc:metadata123',
            'v1.2.3',
            'sha256:' . str_repeat('a', 64),
            'signature123',
            $artifactPath,
            'https://github.com/fairpm/fair-pulse',
            $workspaceDir,
        );

        self::assertSame(
            [['name' => 'johndoe', 'url' => 'https://example.com']],
            $metadata['authors'] ?? null,
        );
    }
}