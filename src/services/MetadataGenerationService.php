<?php

declare(strict_types=1);

namespace FairPulse\Services;

use FAIR\DID\Parsers\MetadataGenerator;
use FAIR\DID\Parsers\PluginHeaderParser;
use FAIR\DID\Parsers\ReadmeParser;

final class MetadataGenerationService
{
    public function generate(
        string $did,
        string $version,
        string $checksum,
        string $signature,
        string $artifactPath,
        string $repoUrl,
        string $workDir,
    ): array {
        $mainPluginFile = $this->findMainPluginFile($workDir);

        $headerParser = new PluginHeaderParser();
        $headerData = $headerParser->parse_file($mainPluginFile);

        $readmeData = [];
        $readmePath = $workDir . '/readme.txt';
        if (file_exists($readmePath)) {
            $readmeParser = new ReadmeParser();
            $readmeData = $readmeParser->parse_file($readmePath);
        }

        $generator = new MetadataGenerator($headerData, $readmeData);
        $generator->set_did($did);

        $artifactFilename = basename($artifactPath);
        $releaseUrl = $repoUrl . "/releases/download/{$version}/{$artifactFilename}";

        $metadata = $generator->generate();
        $metadata['releases'] = [
            [
                'version' => ltrim($version, 'v'),
                'requires' => [
                    'env:php' => '>=' . ($headerData['RequiresPHP'] ?? '7.4'),
                    'env:wp' => '>=' . ($headerData['RequiresAtLeast'] ?? '6.0'),
                ],
                'artifacts' => [
                    'package' => [
                        [
                            'id' => 'main',
                            'url' => $releaseUrl,
                            'content-type' => 'application/zip',
                            'signature' => $signature,
                            'checksum' => $checksum,
                        ],
                    ],
                ],
            ],
        ];

        if (!isset($metadata['@context'])) {
            $metadata['@context'] = 'https://fair.pm/ns/metadata/v1';
        }
        if (!isset($metadata['id'])) {
            $metadata['id'] = $did;
        }
        if (!isset($metadata['type'])) {
            $metadata['type'] = 'wp-plugin';
        }

        return $metadata;
    }

    private function findMainPluginFile(string $workDir): string
    {
        $pluginFiles = glob($workDir . '/*.php');
        if ($pluginFiles === false) {
            throw new \RuntimeException('Could not scan plugin files in workspace.');
        }

        foreach ($pluginFiles as $file) {
            $contents = (string) file_get_contents($file);
            if (strpos($contents, 'Plugin Name:') !== false) {
                return $file;
            }
        }

        throw new \RuntimeException('Could not find main plugin file');
    }
}
