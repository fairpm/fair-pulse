<?php

declare(strict_types=1);

namespace FairPulse\Services;

use FAIR\WordPress\DID\Parsers\MetadataGenerator;
use FAIR\WordPress\DID\Parsers\PluginHeaderParser;
use FAIR\WordPress\DID\Parsers\ReadmeParser;

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

        $metadata = $this->normalizeMetadata(
            $generator->generate(),
            $did,
            $version,
            $checksum,
            $signature,
            $mainPluginFile,
            $releaseUrl,
            $headerData,
            $readmeData,
        );

        (new FairMetadataSchemaValidator())->validate($metadata);

        return $metadata;
    }

    private function normalizeMetadata(
        array $rawMetadata,
        string $did,
        string $version,
        string $checksum,
        string $signature,
        string $mainPluginFile,
        string $releaseUrl,
        array $headerData,
        array $readmeData,
    ): array {
        $slug = $this->firstString(
            $rawMetadata,
            ['slug']
        );
        if ($slug === '') {
            $slug = $this->firstString($headerData, ['text_domain', 'TextDomain']);
        }
        if ($slug === '') {
            $slug = pathinfo($mainPluginFile, PATHINFO_FILENAME);
        }

        $name = $this->firstString($rawMetadata, ['name']);
        if ($name === '') {
            $name = $this->firstString($headerData, ['plugin_name', 'PluginName', 'theme_name', 'ThemeName']);
        }
        if ($name === '') {
            $name = $slug;
        }

        $description = $this->firstString($rawMetadata, ['description']);
        if ($description === '') {
            $description = $this->firstString($readmeData, ['description', 'short_description']);
        }

        $metadata = [
            '@context' => 'https://fair.pm/ns/metadata/v1',
            'id' => $did,
            'type' => $this->normalizeMetadataType($rawMetadata['type'] ?? null),
            'name' => $name,
            'slug' => $slug,
            'filename' => $slug . '/' . basename($mainPluginFile),
            'last_updated' => gmdate('Y-m-d\\TH:i:s\\Z'),
            'releases' => [
                $this->buildRelease($version, $checksum, $signature, $releaseUrl, $headerData, $readmeData),
            ],
        ];

        if ($description !== '') {
            $metadata['description'] = $description;
        }

        $authors = $this->normalizeAuthors($rawMetadata['authors'] ?? null, $headerData);
        if ($authors !== []) {
            $metadata['authors'] = $authors;
        }

        $license = $this->normalizeLicense($rawMetadata['license'] ?? null, $headerData, $readmeData);
        if ($license !== null) {
            $metadata['license'] = $license;
        }

        $security = $this->normalizeSecurity($rawMetadata['security'] ?? null, $headerData);
        if ($security !== []) {
            $metadata['security'] = $security;
        }

        $keywords = $this->normalizeKeywords($rawMetadata['keywords'] ?? ($rawMetadata['tags'] ?? null));
        if ($keywords !== []) {
            $metadata['keywords'] = $keywords;
        }

        $sections = $this->normalizeSections($rawMetadata['sections'] ?? null, $description);
        if ($sections !== []) {
            $metadata['sections'] = $sections;
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

    private function normalizeMetadataType(mixed $type): string
    {
        $value = is_string($type) ? trim($type) : '';
        if ($value === '' || $value === 'plugin') {
            return 'wp-plugin';
        }

        if ($value === 'theme') {
            return 'wp-theme';
        }

        return $value;
    }

    private function buildRelease(
        string $version,
        string $checksum,
        string $signature,
        string $releaseUrl,
        array $headerData,
        array $readmeData,
    ): array {
        $requiresPhp = $this->findRequirement($headerData, $readmeData, ['requires_php', 'RequiresPHP'], '7.4');
        $requiresWp = $this->findRequirement($headerData, $readmeData, ['requires_at_least', 'RequiresAtLeast'], '6.0');

        return [
            'version' => ltrim($version, 'v'),
            'requires' => [
                'env:php' => '>=' . $requiresPhp,
                'env:wp' => '>=' . $requiresWp,
            ],
            'suggests' => new \stdClass(),
            'provides' => [],
            'artifacts' => [
                'package' => [
                    [
                        'url' => $releaseUrl,
                        'content-type' => 'application/zip',
                        'signature' => $signature,
                        'checksum' => $checksum,
                    ],
                ],
            ],
        ];
    }

    private function findRequirement(array $headerData, array $readmeData, array $headerKeys, string $default): string
    {
        $headerValue = $this->firstString($headerData, $headerKeys);
        if ($headerValue !== '') {
            return $headerValue;
        }

        if (isset($readmeData['header']) && is_array($readmeData['header'])) {
            $readmeValue = $this->firstString($readmeData['header'], $headerKeys);
            if ($readmeValue !== '') {
                return $readmeValue;
            }
        }

        return $default;
    }

    private function normalizeAuthors(mixed $authors, array $headerData): array
    {
        if (is_array($authors)) {
            $normalized = [];
            foreach ($authors as $author) {
                if (!is_array($author)) {
                    continue;
                }

                $name = isset($author['name']) && is_string($author['name']) ? trim($author['name']) : '';
                if ($name === '') {
                    continue;
                }

                $entry = ['name' => $name];
                if (isset($author['url']) && is_string($author['url']) && trim($author['url']) !== '') {
                    $entry['url'] = trim($author['url']);
                }
                $normalized[] = $entry;
            }

            if ($normalized !== []) {
                return $this->deduplicateEntries(
                    $normalized,
                    static fn (array $author): string => strtolower($author['name']),
                    static function (array $existing, array $candidate): array {
                        if (
                            !isset($existing['url'])
                            && isset($candidate['url'])
                            && is_string($candidate['url'])
                            && trim($candidate['url']) !== ''
                        ) {
                            $existing['url'] = trim($candidate['url']);
                        }

                        return $existing;
                    }
                );
            }
        }

        $author = $this->firstString($headerData, ['author', 'Author']);
        if ($author === '') {
            return [];
        }

        $normalized = [['name' => $author]];
        $authorUrl = $this->firstString($headerData, ['author_uri', 'AuthorURI']);
        if ($authorUrl !== '') {
            $normalized[0]['url'] = $authorUrl;
        }

        return $this->deduplicateEntries(
            $normalized,
            static fn (array $author): string => strtolower($author['name']),
            static function (array $existing, array $candidate): array {
                if (
                    !isset($existing['url'])
                    && isset($candidate['url'])
                    && is_string($candidate['url'])
                    && trim($candidate['url']) !== ''
                ) {
                    $existing['url'] = trim($candidate['url']);
                }

                return $existing;
            }
        );
    }

    private function deduplicateEntries(array $entries, callable $keyResolver, ?callable $mergeEntry = null): array
    {
        $deduplicated = [];
        $entryIndexes = [];

        foreach ($entries as $entry) {
            $key = $keyResolver($entry);
            if (!is_string($key) || $key === '') {
                continue;
            }

            if (isset($entryIndexes[$key])) {
                if ($mergeEntry !== null) {
                    $index = $entryIndexes[$key];
                    $deduplicated[$index] = $mergeEntry($deduplicated[$index], $entry);
                }

                continue;
            }

            $entryIndexes[$key] = count($deduplicated);
            $deduplicated[] = $entry;
        }

        return $deduplicated;
    }

    private function normalizeLicense(mixed $license, array $headerData, array $readmeData): ?string
    {
        if (is_string($license) && trim($license) !== '') {
            return trim($license);
        }

        if (is_array($license) && isset($license['name']) && is_string($license['name']) && trim($license['name']) !== '') {
            return trim($license['name']);
        }

        $headerLicense = $this->firstString($headerData, ['license', 'License']);
        if ($headerLicense !== '') {
            return $headerLicense;
        }

        if (isset($readmeData['header']) && is_array($readmeData['header'])) {
            $readmeLicense = $this->firstString($readmeData['header'], ['license', 'License']);
            if ($readmeLicense !== '') {
                return $readmeLicense;
            }
        }

        return null;
    }

    private function normalizeSecurity(mixed $security, array $headerData): array
    {
        if (is_array($security)) {
            $normalized = [];
            foreach ($security as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                if (isset($entry['email']) && is_string($entry['email']) && trim($entry['email']) !== '') {
                    $normalized[] = ['email' => trim($entry['email'])];
                    continue;
                }

                if (isset($entry['url']) && is_string($entry['url']) && trim($entry['url']) !== '') {
                    $normalized[] = ['url' => trim($entry['url'])];
                }
            }

            if ($normalized !== []) {
                return $this->deduplicateEntries(
                    $normalized,
                    static function (array $entry): string {
                        $type = array_key_first($entry);
                        if (!is_string($type) || !isset($entry[$type]) || !is_string($entry[$type])) {
                            return '';
                        }

                        return $type . ':' . $entry[$type];
                    }
                );
            }
        }

        $securityValue = $this->firstString($headerData, ['security', 'Security']);
        if ($securityValue === '') {
            return [];
        }

        if (filter_var($securityValue, FILTER_VALIDATE_EMAIL) !== false) {
            return [['email' => $securityValue]];
        }

        if (filter_var($securityValue, FILTER_VALIDATE_URL) !== false) {
            return [['url' => $securityValue]];
        }

        return [];
    }

    private function normalizeKeywords(mixed $keywords): array
    {
        if (!is_array($keywords)) {
            return [];
        }

        $normalized = [];
        foreach ($keywords as $keyword) {
            if (!is_string($keyword)) {
                continue;
            }

            $keyword = trim($keyword);
            if ($keyword === '') {
                continue;
            }

            $normalized[] = $keyword;
        }

        return $this->deduplicateEntries(
            $normalized,
            static fn (string $keyword): string => $keyword,
        );
    }

    private function normalizeSections(mixed $sections, string $description): array
    {
        if (is_array($sections)) {
            $normalized = [];
            foreach ($sections as $key => $value) {
                if (!is_string($key) || !is_string($value) || trim($value) === '') {
                    continue;
                }

                $normalized[$key] = $value;
            }

            if ($normalized !== []) {
                return $normalized;
            }
        }

        if ($description !== '') {
            return ['description' => $description];
        }

        return [];
    }

    private function firstString(array $source, array $keys): string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $source) || !is_string($source[$key])) {
                continue;
            }

            $value = trim($source[$key]);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
