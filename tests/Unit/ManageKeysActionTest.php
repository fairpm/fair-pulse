<?php

declare(strict_types=1);

namespace FairPulse\Tests\Unit;

use FairPulse\Actions\ManageKeysAction;
use FairPulse\Core\ActionRuntime;
use FairPulse\Core\Env;
use FairPulse\Interfaces\LoggerInterface;
use FairPulse\Interfaces\OutputWriterInterface;
use PHPUnit\Framework\TestCase;

final class ManageKeysActionTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->unsetKeyEnv();
        parent::tearDown();
    }

    public function testReturnsFailureAndWritesKeysExistFalseWhenKeysMissing(): void
    {
        $this->unsetKeyEnv();

        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('error');

        $written = [];
        $outputWriter = $this->createMock(OutputWriterInterface::class);
        $outputWriter->method('write')->willReturnCallback(
            static function (string $name, string $value, bool $multiline = false) use (&$written): void {
                $written[$name] = ['value' => $value, 'multiline' => $multiline];
            }
        );

        $runtime = new ActionRuntime(new Env(), $logger, $outputWriter);
        $action = new ManageKeysAction($runtime);
        $exitCode = $action->run();

        self::assertSame(1, $exitCode);
        self::assertSame('false', $written['keys_exist']['value'] ?? null);
    }

    public function testReturnsSuccessAndWritesAllExpectedOutputsWhenKeysPresent(): void
    {
        putenv('FAIR_ROTATION_KEY_PRIVATE=rotation-private');
        putenv('FAIR_ROTATION_KEY_PUBLIC=rotation-public');
        putenv('FAIR_VERIFICATION_KEY_PRIVATE=verification-private');
        putenv('FAIR_VERIFICATION_KEY_PUBLIC=verification-public');
        putenv('FAIR_DID=did:plc:unit-test');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('notice');

        $written = [];
        $outputWriter = $this->createMock(OutputWriterInterface::class);
        $outputWriter->method('write')->willReturnCallback(
            static function (string $name, string $value, bool $multiline = false) use (&$written): void {
                $written[$name] = ['value' => $value, 'multiline' => $multiline];
            }
        );

        $runtime = new ActionRuntime(new Env(), $logger, $outputWriter);
        $action = new ManageKeysAction($runtime);
        $exitCode = $action->run();

        self::assertSame(0, $exitCode);
        self::assertSame('true', $written['keys_exist']['value'] ?? null);
        self::assertSame('rotation-private', $written['rotation_private']['value'] ?? null);
        self::assertSame('verification-private', $written['verification_private']['value'] ?? null);
        self::assertSame('did:plc:unit-test', $written['did']['value'] ?? null);
        self::assertSame('true', $written['did_exists']['value'] ?? null);
    }

    private function unsetKeyEnv(): void
    {
        putenv('FAIR_ROTATION_KEY_PRIVATE');
        putenv('FAIR_ROTATION_KEY_PUBLIC');
        putenv('FAIR_VERIFICATION_KEY_PRIVATE');
        putenv('FAIR_VERIFICATION_KEY_PUBLIC');
        putenv('FAIR_DID');
    }
}
