<?php

declare(strict_types=1);

namespace FairPulse\Tests\E2E;

use FairPulse\Tests\Support\GitHubOutputParser;
use FairPulse\Tests\Support\ScriptRunner;
use PHPUnit\Framework\TestCase;

final class PublishFairActionE2ETest extends TestCase
{
    public function testPublishActionRunsEndToEndWithStubbedGhCli(): void
    {
        $workspace = sys_get_temp_dir() . '/fair-e2e-workspace-' . bin2hex(random_bytes(5));
        mkdir($workspace, 0777, true);

        file_put_contents(
            $workspace . '/example-plugin.php',
            "<?php\n/*\nPlugin Name: E2E Plugin\nRequires PHP: 8.1\nRequires at least: 6.5\n*/\n"
        );
        file_put_contents($workspace . '/readme.txt', "E2E plugin readme.\n");

        $fakeBinDir = sys_get_temp_dir() . '/fair-e2e-bin-' . bin2hex(random_bytes(5));
        mkdir($fakeBinDir, 0777, true);
        $ghPath = $fakeBinDir . '/gh';

        $ghScript = <<<'BASH'
#!/usr/bin/env bash
set -e

if [[ "$1" == "release" && "$2" == "download" ]]; then
  OUT=""
  for ((i=1; i<=$#; i++)); do
    ARG="${!i}"
    if [[ "$ARG" == "-O" ]]; then
      NEXT=$((i+1))
      OUT="${!NEXT}"
    fi
  done

  if [[ -z "$OUT" ]]; then
    echo "missing output path" >&2
    exit 1
  fi

  echo "fake-zip-content" > "$OUT"
  exit 0
fi

if [[ "$1" == "release" && "$2" == "upload" ]]; then
  exit 0
fi

echo "unsupported gh command: $*" >&2
exit 1
BASH;

        file_put_contents($ghPath, $ghScript);
        chmod($ghPath, 0755);

        $outputFile = tempnam(sys_get_temp_dir(), 'fair-e2e-output-');
        $path = $fakeBinDir . ':' . (getenv('PATH') ?: '/usr/bin:/bin');

        $result = ScriptRunner::run(
            __DIR__ . '/../../src/actions/PublishFairAction.php',
            [
                'PATH' => $path,
                'GITHUB_OUTPUT' => $outputFile,
                'GITHUB_WORKSPACE' => $workspace,
                'GITHUB_REPOSITORY' => 'fairpm/fair-pulse',
                'GITHUB_SERVER_URL' => 'https://github.com',
                'GITHUB_TOKEN' => 'dummy-token',
                'INPUT_VERSION' => 'v1.2.3',
                'INPUT_ARTIFACT_NAME' => 'fair-pulse.zip',
                'INPUT_UPLOAD_METADATA' => 'true',
                'INPUT_UPDATE_DID_SERVICE' => 'true',
                'FAIR_ROTATION_KEY_PRIVATE' => 'rotation-private',
                'FAIR_ROTATION_KEY_PUBLIC' => 'rotation-public',
                'FAIR_VERIFICATION_KEY_PRIVATE' => 'verification-private',
                'FAIR_VERIFICATION_KEY_PUBLIC' => 'verification-public',
                'FAIR_DID' => '',
            ]
        );

        self::assertSame(0, $result->exitCode, $result->stdout . "\n" . $result->stderr);

        $outputs = GitHubOutputParser::parseFile($outputFile);
        self::assertSame('v1.2.3', $outputs['version'] ?? null);
        self::assertArrayHasKey('did', $outputs);
        self::assertArrayHasKey('metadata_path', $outputs);

        $metadataPath = $outputs['metadata_path'] ?? '/tmp/fair-metadata.json';
        self::assertFileExists($metadataPath);

        $metadata = json_decode((string) file_get_contents($metadataPath), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('did:plc:', substr((string) ($metadata['id'] ?? ''), 0, 8));
    }
}
