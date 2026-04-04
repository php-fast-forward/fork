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

namespace FastForward\Fork\Tests\Manager;

use stdClass;
use FastForward\Fork\Exception\InvalidArgumentException;
use FastForward\Fork\Exception\LogicException;
use FastForward\Fork\Manager\ForkManager;
use FastForward\Fork\Manager\ForkManagerInterface;
use FastForward\Fork\Signal\Signal;
use FastForward\Fork\Signal\SignalHandlerInterface;
use FastForward\Fork\Tests\Support\ReflectsNonPublicMembers;
use FastForward\Fork\Tests\Support\SpyLogger;
use FastForward\Fork\Worker\Worker;
use FastForward\Fork\Worker\WorkerInterface;
use FastForward\Fork\Worker\WorkerState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function getmypid;
use function posix_getpid;
use function posix_kill;
use function usleep;

#[CoversClass(ForkManager::class)]
final class ForkManagerTest extends TestCase
{
    use ReflectsNonPublicMembers;

    /**
     * @return void
     */
    #[Test]
    public function itWillReportTheCurrentProcessAsTheMasterWhenConstructed(): void
    {
        $manager = $this->createManager();

        self::assertTrue($manager->isSupported());
        self::assertSame(posix_getpid(), $manager->getMasterPid());
        self::assertTrue($manager->isMaster());
        self::assertFalse($manager->isWorker());
    }

    /**
     * @return void
     */
    #[Test]
    public function itWillRegisterConfiguredSignalHandlers(): void
    {
        $handler = new class implements SignalHandlerInterface {
            public array $calls = [];

            /**
             * @return array
             */
            public function signals(): array
            {
                return [Signal::User1];
            }

            /**
             * @param ForkManagerInterface $manager
             * @param Signal $signal
             *
             * @return void
             */
            public function __invoke(ForkManagerInterface $manager, Signal $signal): void
            {
                $this->calls[] = [$manager, $signal];
            }
        };

        $manager = new ForkManager($handler);

        posix_kill(getmypid(), Signal::User1->value);
        usleep(10_000);

        self::assertCount(1, $handler->calls);
        self::assertSame($manager, $handler->calls[0][0]);
        self::assertSame(Signal::User1, $handler->calls[0][1]);
    }

    /**
     * @return void
     */
    #[Test]
    public function forkWillRejectInvalidWorkerCounts(): void
    {
        $manager = $this->createManager();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The worker count must be greater than zero, 0 given.');

        $manager->fork(static fn(): null => null, 0);
    }

    /**
     * @return void
     */
    #[Test]
    public function forkWillRejectCallsFromAWorkerContext(): void
    {
        $manager = $this->createManager();
        $this->setNonPublicProperty($manager, 'masterPid', \PHP_INT_MAX);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Forking from a worker process is not supported by this manager instance.');

        $manager->fork(static fn(): null => null);
    }

    /**
     * @return void
     */
    #[Test]
    public function forkWillCreateWorkersAndWaitForThemToFinish(): void
    {
        $logger = new SpyLogger();
        $manager = $this->createManager(logger: $logger);

        $group = $manager->fork(
            static function (WorkerInterface $worker): int {
                echo 'worker:' . $worker->getPid();
                error_reporting(\E_ALL);
                trigger_error('warning', \E_USER_WARNING);

                return 5;
            },
            2,
        );

        $group->wait();

        self::assertCount(2, $group);

        foreach ($group->all() as $pid => $worker) {
            self::assertSame($pid, $worker->getPid());
            self::assertFalse($worker->isRunning());
            self::assertSame(5, $worker->getExitCode());
            self::assertNull($worker->getTerminationSignal());
            self::assertStringContainsString('worker:' . $pid, $worker->getOutput());
            self::assertStringContainsString('PHP error ' . \E_USER_WARNING . ': warning', $worker->getErrorOutput());
        }

        self::assertTrue($logger->hasInfo('Forked worker process.'));
        self::assertTrue($logger->hasInfo('Worker process terminated.'));
    }

    /**
     * @return void
     */
    #[Test]
    public function killWillTargetSpecificWorkers(): void
    {
        $manager = $this->createManager();
        $group = $manager->fork(static function (): void {
            usleep(500_000);
        });
        $worker = array_values($group->all())[0];

        $manager->kill(Signal::Terminate, $worker);
        $manager->wait($worker);

        self::assertSame(Signal::Terminate, $worker->getTerminationSignal());
    }

    /**
     * @return void
     */
    #[Test]
    public function waitAndKillWithoutArgumentsWillOperateOnAllManagedWorkers(): void
    {
        $manager = $this->createManager();
        $group = $manager->fork(static function (): void {
            usleep(500_000);
        }, 2);

        $manager->kill();
        $manager->wait();

        foreach ($group->all() as $worker) {
            self::assertFalse($worker->isRunning());
            self::assertSame(Signal::Terminate, $worker->getTerminationSignal());
        }
    }

    /**
     * @return void
     */
    #[Test]
    public function waitAndKillWillDoNothingWhenNoWorkersExist(): void
    {
        $manager = $this->createManager();

        $manager->wait();
        $manager->kill();

        $this->addToAssertionCount(1);
    }

    /**
     * @return void
     */
    #[Test]
    public function waitWillRejectUnsupportedWorkerImplementations(): void
    {
        $manager = $this->createManager();
        $worker = new class implements WorkerInterface {
            /**
             * @return int
             */
            public function getPid(): int
            {
                return 123;
            }

            /**
             * @return bool
             */
            public function isRunning(): bool
            {
                return false;
            }

            /**
             * @return int|null
             */
            public function getStatus(): ?int
            {
                return null;
            }

            /**
             * @return int|null
             */
            public function getExitCode(): ?int
            {
                return null;
            }

            /**
             * @return Signal|null
             */
            public function getTerminationSignal(): ?Signal
            {
                return null;
            }

            /**
             * @return string
             */
            public function getOutput(): string
            {
                return '';
            }

            /**
             * @return string
             */
            public function getErrorOutput(): string
            {
                return '';
            }

            /**
             * @return void
             */
            public function wait(): void {}

            /**
             * @param Signal $signal
             *
             * @return void
             */
            public function kill(Signal $signal = Signal::Terminate): void {}
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not supported by this fork manager.');

        $manager->wait($worker);
    }

    /**
     * @return void
     */
    #[Test]
    public function waitWillRejectForeignWorkers(): void
    {
        $owner = $this->createManager();
        $manager = $this->createManager();
        $group = $owner->fork(static function (): void {
            usleep(500_000);
        });
        $worker = array_values($group->all())[0];

        try {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('is not managed by this fork manager.');

            $manager->wait($worker);
        } finally {
            $owner->kill();
            $owner->wait();
        }
    }

    /**
     * @return void
     */
    #[Test]
    public function killWillRejectForeignWorkerGroups(): void
    {
        $owner = $this->createManager();
        $manager = $this->createManager();
        $group = $owner->fork(static function (): void {
            usleep(500_000);
        });

        try {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('The provided worker group is not managed by this fork manager.');

            $manager->kill(Signal::Terminate, $group);
        } finally {
            $owner->kill();
            $owner->wait();
        }
    }

    /**
     * @return void
     */
    #[Test]
    public function privateWaitLogicWillRejectWorkersTryingToWaitOnThemselves(): void
    {
        $manager = $this->createManager();
        $this->setNonPublicProperty($manager, 'masterPid', \PHP_INT_MAX);

        $state = WorkerState::create();
        $state->activateParent(getmypid());

        $worker = $this->instantiateWithoutConstructor(Worker::class, [
            'manager' => $manager,
            'state' => $state,
            'callback' => static fn(): null => null,
            'logger' => null,
        ]);

        $this->setNonPublicProperty($manager, 'statesByPid', [
            getmypid() => $state,
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('cannot wait on itself');

        $this->invokeNonPublicMethod($manager, 'waitOnWorkers', [
            getmypid() => $worker,
        ]);
    }

    /**
     * @return void
     */
    #[Test]
    public function privateCollectExitedWorkersWillDetachTrackedStatesWhenThereAreNoChildren(): void
    {
        $manager = $this->createManager();
        $state = WorkerState::create();
        $state->activateParent(999_999_999);

        $this->setNonPublicProperty($manager, 'statesByPid', [
            999_999_999 => $state,
        ]);

        $this->invokeNonPublicMethod($manager, 'collectExitedWorkers');

        self::assertFalse($state->isRunning());
    }

    /**
     * @return void
     */
    #[Test]
    public function privateWaitLogicWillSkipStoppedStatesAndRetryWhenNoStreamsAreAvailable(): void
    {
        $pid = pcntl_fork();

        if (0 === $pid) {
            usleep(150_000);
            exit(0);
        }

        $manager = $this->createManager();
        $stoppedState = new class {
            /**
             * @return bool
             */
            public function isRunning(): bool
            {
                return false;
            }
        };
        $runningState = new class ($pid) {
            public bool $running = true;

            /**
             * @param int $pid
             */
            public function __construct(
                private readonly int $pid
            ) {}

            /**
             * @return bool
             */
            public function isRunning(): bool
            {
                return $this->running;
            }

            /**
             * @return array
             */
            public function getReadableStreams(): array
            {
                return [];
            }

            /**
             * @return int
             */
            public function getPid(): int
            {
                return $this->pid;
            }

            /**
             * @param array $readableStreams
             * @param bool $final
             * @param mixed $logger
             *
             * @return void
             */
            public function drainOutput(array $readableStreams = [], bool $final = false, mixed $logger = null): void {}

            /**
             * @param mixed $logger
             *
             * @return void
             */
            public function markDetached(mixed $logger = null): void
            {
                $this->running = false;
            }

            /**
             * @param int $status
             * @param mixed $logger
             *
             * @return void
             */
            public function markTerminated(int $status, mixed $logger = null): void
            {
                $this->running = false;
            }
        };

        $this->setNonPublicProperty($manager, 'statesByPid', [
            10 => $stoppedState,
            $pid => $runningState,
        ]);

        $this->invokeNonPublicMethod($manager, 'waitOnWorkers', [
            $pid => new stdClass(),
        ]);

        self::assertFalse($runningState->isRunning());
    }

    /**
     * @return void
     */
    #[Test]
    public function privateWaitLogicWillIgnoreSelectedStreamsThatAreNotMappedToWorkerStateInstances(): void
    {
        $pid = pcntl_fork();

        if (0 === $pid) {
            usleep(150_000);
            exit(0);
        }

        $pair = stream_socket_pair(\STREAM_PF_UNIX, \STREAM_SOCK_STREAM, 0);
        fwrite($pair[1], 'stdout');
        fclose($pair[1]);

        $manager = $this->createManager();
        $fakeState = new class ($pid, $pair[0]) {
            public bool $running = true;

            /**
             * @param int $pid
             * @param mixed $stream
             */
            public function __construct(
                private readonly int $pid,
                private $stream
            ) {}

            /**
             * @return bool
             */
            public function isRunning(): bool
            {
                return $this->running;
            }

            /**
             * @return array
             */
            public function getReadableStreams(): array
            {
                return [$this->stream];
            }

            /**
             * @return int
             */
            public function getPid(): int
            {
                return $this->pid;
            }

            /**
             * @param array $readableStreams
             * @param bool $final
             * @param mixed $logger
             *
             * @return void
             */
            public function drainOutput(array $readableStreams = [], bool $final = false, mixed $logger = null): void {}

            /**
             * @param mixed $logger
             *
             * @return void
             */
            public function markDetached(mixed $logger = null): void
            {
                $this->running = false;
                fclose($this->stream);
            }

            /**
             * @param int $status
             * @param mixed $logger
             *
             * @return void
             */
            public function markTerminated(int $status, mixed $logger = null): void
            {
                $this->running = false;

                if (\is_resource($this->stream)) {
                    fclose($this->stream);
                }
            }
        };

        $this->setNonPublicProperty($manager, 'statesByPid', [
            $pid => $fakeState,
        ]);

        $this->invokeNonPublicMethod($manager, 'waitOnWorkers', [
            $pid => new stdClass(),
        ]);

        self::assertFalse($fakeState->isRunning());
    }

    /**
     * @return void
     */
    #[Test]
    public function privateHelpersWillRecognizeRelevantWaitErrorCodes(): void
    {
        $manager = $this->createManager();

        self::assertTrue(
            $this->invokeNonPublicMethod($manager, 'isInterruptedWait', \defined('PCNTL_EINTR') ? \PCNTL_EINTR : 4)
        );
        self::assertTrue(
            $this->invokeNonPublicMethod($manager, 'isNoChildError', \defined('PCNTL_ECHILD') ? \PCNTL_ECHILD : 10)
        );
    }

    /**
     * @param SignalHandlerInterface|null $signalHandler
     * @param SpyLogger|null $logger
     *
     * @return ForkManager
     */
    private function createManager(
        ?SignalHandlerInterface $signalHandler = null,
        ?SpyLogger $logger = null,
    ): ForkManager {
        return new ForkManager($signalHandler ?? new class implements SignalHandlerInterface {
            /**
             * @return array
             */
            public function signals(): array
            {
                return [];
            }

            /**
             * @param ForkManagerInterface $manager
             * @param Signal $signal
             *
             * @return void
             */
            public function __invoke(ForkManagerInterface $manager, Signal $signal): void {}
        }, $logger);
    }
}
