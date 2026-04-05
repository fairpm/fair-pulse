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

    public function testPublishActionFallsBackToNonPrefixedReleaseTag(): void
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
  TAG="$3"
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

  # Simulate a release that exists only without v-prefix.
  if [[ "$TAG" != "1.2.3" ]]; then
    echo "release not found" >&2
    exit 1
  fi

  echo "fake-zip-content" > "$OUT"
  exit 0
fi

if [[ "$1" == "release" && "$2" == "upload" ]]; then
  TAG="$3"
  if [[ "$TAG" != "1.2.3" ]]; then
    echo "release not found" >&2
    exit 1
  fi
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
        self::assertSame('1.2.3', $outputs['version'] ?? null);
        self::assertArrayHasKey('did', $outputs);
        self::assertArrayHasKey('metadata_path', $outputs);
    }

      public function testPublishActionBuildsAndUploadsMissingAssetFromWorkspace(): void
      {
        $workspace = sys_get_temp_dir() . '/fair-e2e-workspace-' . bin2hex(random_bytes(5));
        mkdir($workspace, 0777, true);

        file_put_contents(
          $workspace . '/example-plugin.php',
          "<?php\n/*\nPlugin Name: E2E Plugin\nRequires PHP: 8.1\nRequires at least: 6.5\n*/\n"
        );
        file_put_contents($workspace . '/readme.txt', "E2E plugin readme.\n");

        $initialized = ScriptRunner::run(
          __DIR__ . '/../../src/actions/ManageKeysAction.php',
          [
            'GITHUB_OUTPUT' => tempnam(sys_get_temp_dir(), 'fair-init-output-') ?: '',
            'FAIR_ROTATION_KEY_PRIVATE' => 'rotation-private',
            'FAIR_ROTATION_KEY_PUBLIC' => 'rotation-public',
            'FAIR_VERIFICATION_KEY_PRIVATE' => 'verification-private',
            'FAIR_VERIFICATION_KEY_PUBLIC' => 'verification-public',
            'FAIR_DID' => '',
          ]
        );

        self::assertSame(0, $initialized->exitCode, $initialized->stdout . "\n" . $initialized->stderr);

        $initProc = proc_open(
          ['git', 'init'],
          [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
          $pipes,
          $workspace
        );
        self::assertTrue(is_resource($initProc));
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($initProc));

        $configProc = proc_open(
          ['git', 'config', 'user.email', 'ci@example.com'],
          [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
          $pipes,
          $workspace
        );
        self::assertTrue(is_resource($configProc));
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($configProc));

        $configProc = proc_open(
          ['git', 'config', 'user.name', 'CI'],
          [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
          $pipes,
          $workspace
        );
        self::assertTrue(is_resource($configProc));
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($configProc));

        $addProc = proc_open(
          ['git', 'add', '.'],
          [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
          $pipes,
          $workspace
        );
        self::assertTrue(is_resource($addProc));
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($addProc));

        $commitProc = proc_open(
          ['git', 'commit', '-m', 'init'],
          [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
          $pipes,
          $workspace
        );
        self::assertTrue(is_resource($commitProc));
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($commitProc));

        $fakeBinDir = sys_get_temp_dir() . '/fair-e2e-bin-' . bin2hex(random_bytes(5));
        mkdir($fakeBinDir, 0777, true);
        $ghPath = $fakeBinDir . '/gh';
        $artifactUploadMarker = $workspace . '/artifact-uploaded.flag';

        $ghScript = <<<'BASH'
    #!/usr/bin/env bash
    set -e

    if [[ "$1" == "release" && "$2" == "download" ]]; then
      # Simulate release existing but requested asset missing.
      echo "asset not found" >&2
      exit 1
    fi

    if [[ "$1" == "release" && "$2" == "view" ]]; then
      # Release exists.
      exit 0
    fi

    if [[ "$1" == "release" && "$2" == "create" ]]; then
      exit 1
    fi

    if [[ "$1" == "release" && "$2" == "upload" ]]; then
      # Mark upload call so test can assert fallback path used.
      touch "${FAIR_UPLOAD_MARKER}"
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
            'FAIR_UPLOAD_MARKER' => $artifactUploadMarker,
            'GITHUB_OUTPUT' => $outputFile,
            'GITHUB_WORKSPACE' => $workspace,
            'GITHUB_REPOSITORY' => 'fairpm/fair-pulse',
            'GITHUB_SERVER_URL' => 'https://github.com',
            'GITHUB_TOKEN' => 'dummy-token',
            'GITHUB_SHA' => '0123456789abcdef0123456789abcdef01234567',
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
        self::assertFileExists($artifactUploadMarker);

        $outputs = GitHubOutputParser::parseFile($outputFile);
        self::assertSame('v1.2.3', $outputs['version'] ?? null);
        self::assertArrayHasKey('did', $outputs);
      }
}
