<?php

declare(strict_types=1);

/**
 * This file is part of fast-forward/fork.
 *
 * This source file is subject to the license bundled
 * with this source code in the file LICENSE.
 *
 * @copyright Copyright (c) 2026 Felipe Sayão Lobato Abreu <github@mentordosnerds.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 *
 * @see       https://github.com/php-fast-forward/fork
 * @see       https://github.com/php-fast-forward
 * @see       https://datatracker.ietf.org/doc/html/rfc2119
 */

namespace FastForward\Fork\Tests\Worker;

use RuntimeException;
use BadMethodCallException;
use Closure;
use FastForward\Fork\Exception\LogicException;
use FastForward\Fork\Manager\ForkManagerInterface;
use FastForward\Fork\Signal\Signal;
use FastForward\Fork\Tests\Support\ReflectsNonPublicMembers;
use FastForward\Fork\Tests\Support\SpyLogger;
use FastForward\Fork\Worker\Worker;
use FastForward\Fork\Worker\WorkerGroupInterface;
use FastForward\Fork\Worker\WorkerInterface;
use FastForward\Fork\Worker\WorkerState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;

use function getmypid;
use function pcntl_waitpid;
use function posix_kill;
use function trigger_error;
use function usleep;

#[CoversClass(Worker::class)]
final class WorkerTest extends TestCase
{
    use ProphecyTrait;
    use ReflectsNonPublicMembers;

    /**
     * @return void
     */
    #[Test]
    public function accessorsWillReturnTheUnderlyingStateValuesWhenTheWorkerIsNotRunning(): void
    {
        $state = WorkerState::create();

        $this->setNonPublicProperty($state, 'pid', 123);
        $state->writeOutput('stdout');
        $state->writeErrorOutput('stderr');
        $state->closeChildSide();
        $state->drainOutput(final: true);
        $this->setNonPublicProperty($state, 'running', false);
        $this->setNonPublicProperty($state, 'status', 7);
        $this->setNonPublicProperty($state, 'exitCode', 7);

        $worker = $this->createWorker($this->createManagerStub(), $state, static fn(): null => null);

        self::assertSame(123, $worker->getPid());
        self::assertFalse($worker->isRunning());
        self::assertSame(7, $worker->getStatus());
        self::assertSame(7, $worker->getExitCode());
        self::assertNull($worker->getTerminationSignal());
        self::assertSame('stdout', $worker->getOutput());
        self::assertSame('stderr', $worker->getErrorOutput());

        $worker->wait();

        self::assertFalse($worker->isRunning());
    }

    /**
     * @return void
     */
    #[Test]
    public function waitWillDelegateToTheManagerWhenTheWorkerIsRunningInTheMasterProcess(): void
    {
        $state = WorkerState::create();
        $manager = $this->prophesize(ForkManagerInterface::class);
        $manager->isWorker()
            ->willReturn(false);

        $worker = $this->createWorker($manager->reveal(), $state, static fn(): null => null);

        $manager->wait(Argument::that(
            static fn(mixed $value): bool => $value instanceof Worker && 0 === $value->getPid(),
        ))->shouldBeCalledOnce();

        $worker->wait();
    }

    /**
     * @return void
     */
    #[Test]
    public function waitWillThrowWhenCalledFromTheCurrentWorkerProcess(): void
    {
        $state = WorkerState::create();
        $this->setNonPublicProperty($state, 'pid', getmypid());

        $worker = $this->createWorker($this->createManagerStub(isWorker: true), $state, static fn(): null => null);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Worker ' . getmypid() . ' cannot wait on itself.');

        $worker->wait();
    }

    /**
     * @return void
     */
    #[Test]
    public function killWillIgnoreStoppedWorkers(): void
    {
        $state = WorkerState::create();
        $this->setNonPublicProperty($state, 'running', false);

        $worker = $this->createWorker($this->createManagerStub(), $state, static fn(): null => null);

        $worker->kill();

        self::assertFalse($state->isRunning());
    }

    /**
     * @return void
     */
    #[Test]
    public function killWillSendTheProvidedSignalToTheRunningProcess(): void
    {
        $pid = pcntl_fork();

        if (0 === $pid) {
            usleep(500_000);
            exit(0);
        }

        $state = WorkerState::create();
        $state->activateParent($pid);

        $worker = $this->createWorker($this->createManagerStub(), $state, static fn(): null => null);

        $worker->kill(Signal::Terminate);

        $status = 0;
        pcntl_waitpid($pid, $status);

        self::assertTrue(pcntl_wifsignaled($status));
        self::assertSame(Signal::Terminate->value, pcntl_wtermsig($status));
    }

    /**
     * @return void
     */
    #[Test]
    public function killWillMarkTheWorkerAsDetachedWhenTheProcessDoesNotExist(): void
    {
        $pid = pcntl_fork();

        if (0 === $pid) {
            exit(0);
        }

        pcntl_waitpid($pid, $status);

        $state = WorkerState::create();
        $state->activateParent($pid);

        $logger = new SpyLogger();
        $worker = $this->createWorker($this->createManagerStub(), $state, static fn(): null => null, $logger);

        $worker->kill();

        self::assertFalse($state->isRunning());
        self::assertTrue($logger->hasWarning('Worker process detached before wait completion.'));
    }

    /**
     * @return void
     */
    #[Test]
    public function constructorWillForkTheWorkerAndInitializeTheParentSideState(): void
    {
        $state = WorkerState::create();
        $logger = new SpyLogger();

        $worker = new Worker(
            $this->createManagerStub(),
            $state,
            static function (): int {
                usleep(500_000);

                return 0;
            },
            $logger,
        );

        self::assertGreaterThan(0, $worker->getPid());
        self::assertTrue($logger->hasInfo('Forked worker process.'));

        posix_kill($worker->getPid(), Signal::Terminate->value);
        pcntl_waitpid($worker->getPid(), $status);
    }

    /**
     * @return void
     */
    #[Test]
    public function executeCallbackWillCaptureBufferedOutputAndPhpWarnings(): void
    {
        $state = WorkerState::create();
        $this->setNonPublicProperty($state, 'pid', 432);
        $worker = $this->createWorker(
            $this->createManagerStub(),
            $state,
            static function (): bool {
                echo 'stdout';
                error_reporting(\E_ALL);
                trigger_error('warning', \E_USER_WARNING);

                return false;
            },
        );

        $exitCode = $this->invokeNonPublicMethod($worker, 'executeCallback');
        $state->drainOutput(final: true);

        self::assertSame(1, $exitCode);
        self::assertSame('stdout', $state->getOutput());
        self::assertStringContainsString('PHP error ' . \E_USER_WARNING . ': warning', $state->getErrorOutput());
    }

    /**
     * @return void
     */
    #[Test]
    public function executeCallbackWillIgnoreSuppressedErrors(): void
    {
        $state = WorkerState::create();
        $this->setNonPublicProperty($state, 'pid', 433);
        $worker = $this->createWorker(
            $this->createManagerStub(),
            $state,
            static function (): int {
                error_reporting(0);
                trigger_error('suppressed', \E_USER_WARNING);

                return 0;
            },
        );

        $exitCode = $this->invokeNonPublicMethod($worker, 'executeCallback');
        $state->drainOutput(final: true);

        self::assertSame(0, $exitCode);
        self::assertSame('', $state->getErrorOutput());
    }

    /**
     * @param int $throwableCode
     * @param int $expectedExitCode
     *
     * @return void
     *
     * @throws RuntimeException
     */
    #[Test]
    #[DataProvider('provideThrowableExitCodes')]
    public function executeCallbackWillConvertThrowablesIntoNormalizedExitCodes(
        int $throwableCode,
        int $expectedExitCode
    ): void {
        $state = WorkerState::create();
        $this->setNonPublicProperty($state, 'pid', 654);
        $worker = $this->createWorker(
            $this->createManagerStub(),
            $state,
            static fn(): never => throw new RuntimeException('boom', $throwableCode),
        );

        $exitCode = $this->invokeNonPublicMethod($worker, 'executeCallback');
        $state->drainOutput(final: true);

        self::assertSame($expectedExitCode, $exitCode);
        self::assertStringContainsString('RuntimeException: boom', $state->getErrorOutput());
    }

    /**
     * @param mixed $result
     * @param int $expectedExitCode
     *
     * @return void
     */
    #[Test]
    #[DataProvider('provideNormalizedExitCodes')]
    public function normalizeExitCodeWillClampAndNormalizeDifferentCallbackResults(
        mixed $result,
        int $expectedExitCode
    ): void {
        $worker = $this->createWorker(
            $this->createManagerStub(),
            WorkerState::create(),
            static fn(): null => null,
        );

        self::assertSame($expectedExitCode, $this->invokeNonPublicMethod($worker, 'normalizeExitCode', $result));
    }

    /**
     * @return void
     */
    #[Test]
    public function pollStateWillReturnWithoutChangingStateWhileTheChildIsStillRunning(): void
    {
        $pid = pcntl_fork();

        if (0 === $pid) {
            usleep(500_000);
            exit(0);
        }

        $state = WorkerState::create();
        $state->activateParent($pid);

        $worker = $this->createWorker($this->createManagerStub(), $state, static fn(): null => null);

        self::assertNull($worker->getExitCode());
        self::assertTrue($state->isRunning());

        posix_kill($pid, Signal::Terminate->value);
        pcntl_waitpid($pid, $status);
    }

    /**
     * @return void
     */
    #[Test]
    public function pollStateWillMarkTheWorkerAsTerminatedOnceTheChildHasExited(): void
    {
        $pid = pcntl_fork();

        if (0 === $pid) {
            exit(7);
        }

        $state = WorkerState::create();
        $state->activateParent($pid);

        $worker = $this->createWorker($this->createManagerStub(), $state, static fn(): null => null);

        usleep(50_000);

        self::assertFalse($worker->isRunning());
        self::assertSame(7, $worker->getExitCode());
        self::assertNull($worker->getTerminationSignal());
    }

    /**
     * @return void
     */
    #[Test]
    public function pollStateWillMarkTheWorkerAsDetachedWhenNoChildProcessExists(): void
    {
        $state = WorkerState::create();
        $state->activateParent(999_999_999);

        $logger = new SpyLogger();
        $worker = $this->createWorker($this->createManagerStub(), $state, static fn(): null => null, $logger);

        self::assertNull($worker->getStatus());
        self::assertFalse($state->isRunning());
        self::assertTrue($logger->hasWarning('Worker process detached before wait completion.'));
    }

    /**
     * @return iterable
     */
    public static function provideThrowableExitCodes(): iterable
    {
        yield 'positive code' => [42, 42];
        yield 'non-positive code' => [0, 255];
    }

    /**
     * @return iterable
     */
    public static function provideNormalizedExitCodes(): iterable
    {
        yield 'negative integer' => [-1, 0];
        yield 'large integer' => [999, 255];
        yield 'false' => [false, 1];
        yield 'truthy value' => ['value', 0];
    }

    /**
     * @param ForkManagerInterface $manager
     * @param WorkerState $state
     * @param callable $callback
     * @param LoggerInterface|null $logger
     *
     * @return Worker
     */
    private function createWorker(
        ForkManagerInterface $manager,
        WorkerState $state,
        callable $callback,
        ?LoggerInterface $logger = null,
    ): Worker {
        return $this->instantiateWithoutConstructor(Worker::class, [
            'manager' => $manager,
            'state' => $state,
            'callback' => Closure::fromCallable($callback),
            'logger' => $logger,
        ]);
    }

    /**
     * @param bool $isWorker
     *
     * @return ForkManagerInterface
     *
     * @throws BadMethodCallException
     */
    private function createManagerStub(bool $isWorker = false): ForkManagerInterface
    {
        return new readonly class ($isWorker) implements ForkManagerInterface {
            /**
             * @param bool $worker
             */
            public function __construct(
                private bool $worker
            ) {}

            /**
             * @return bool
             */
            public function isSupported(): bool
            {
                return true;
            }

            /**
             * @param callable $workerCallback
             * @param int $workerCount
             *
             * @return WorkerGroupInterface
             *
             * @throws BadMethodCallException
             */
            public function fork(callable $workerCallback, int $workerCount = 1): WorkerGroupInterface
            {
                throw new BadMethodCallException();
            }

            /**
             * @param WorkerInterface|WorkerGroupInterface $workers
             *
             * @return void
             */
            public function wait(WorkerInterface|WorkerGroupInterface ...$workers): void {}

            /**
             * @param Signal $signal
             * @param WorkerInterface|WorkerGroupInterface $workers
             *
             * @return void
             */
            public function kill(
                Signal $signal = Signal::Terminate,
                WorkerInterface|WorkerGroupInterface ...$workers,
            ): void {}

            /**
             * @return int
             */
            public function getMasterPid(): int
            {
                return 1;
            }

            /**
             * @return bool
             */
            public function isMaster(): bool
            {
                return ! $this->worker;
            }

            /**
             * @return bool
             */
            public function isWorker(): bool
            {
                return $this->worker;
            }
        };
    }
}
