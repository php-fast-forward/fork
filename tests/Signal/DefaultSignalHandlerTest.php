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

namespace FastForward\Fork\Tests\Signal;

use FastForward\Fork\Worker\WorkerGroupInterface;
use BadMethodCallException;
use FastForward\Fork\Worker\WorkerInterface;
use FastForward\Fork\Manager\ForkManagerInterface;
use FastForward\Fork\Signal\DefaultSignalHandler;
use FastForward\Fork\Signal\Signal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

#[CoversClass(DefaultSignalHandler::class)]
final class DefaultSignalHandlerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @return void
     */
    #[Test]
    public function signalsWillReturnTheConfiguredSignals(): void
    {
        $handler = new DefaultSignalHandler([Signal::User1, Signal::User2], exitOnSignal: false);

        self::assertSame([Signal::User1, Signal::User2], $handler->signals());
    }

    /**
     * @return void
     */
    #[Test]
    public function invokeWillPropagateTerminationSignalsAndWaitForWorkersWhenConfigured(): void
    {
        $manager = $this->prophesize(ForkManagerInterface::class);
        $manager->isWorker()
            ->willReturn(false);
        $manager->isMaster()
            ->willReturn(true);
        $manager->kill(Signal::Terminate)->shouldBeCalledOnce();
        $manager->wait()
            ->shouldBeCalledOnce();

        $handler = new DefaultSignalHandler(waitForWorkers: true, exitOnSignal: false);

        $handler($manager->reveal(), Signal::Interrupt);
    }

    /**
     * @return void
     */
    #[Test]
    public function invokeWillPropagateTheOriginalSignalWhenNoNormalizationIsNeeded(): void
    {
        $manager = $this->prophesize(ForkManagerInterface::class);
        $manager->isWorker()
            ->willReturn(false);
        $manager->isMaster()
            ->willReturn(true);
        $manager->kill(Signal::User1)->shouldBeCalledOnce();
        $manager->wait()
            ->shouldNotBeCalled();

        $handler = new DefaultSignalHandler(waitForWorkers: false, exitOnSignal: false);

        $handler($manager->reveal(), Signal::User1);
    }

    /**
     * @return void
     */
    #[Test]
    public function invokeWillNotSignalWorkersWhenTheCurrentProcessIsNotTheMaster(): void
    {
        $manager = $this->prophesize(ForkManagerInterface::class);
        $manager->isWorker()
            ->willReturn(false);
        $manager->isMaster()
            ->willReturn(false);
        $manager->kill(Argument::cetera())->shouldNotBeCalled();
        $manager->wait()
            ->shouldNotBeCalled();

        $handler = new DefaultSignalHandler(waitForWorkers: true, exitOnSignal: false);

        $handler($manager->reveal(), Signal::Terminate);
    }

    /**
     * @return void
     *
     * @throws BadMethodCallException
     */
    #[Test]
    public function invokeWillEscalateSignalsWhenHandlingIsAlreadyInProgress(): void
    {
        $handler = new DefaultSignalHandler(
            waitForWorkers: false,
            exitOnSignal: false,
            escalationSignal: Signal::Kill,
        );

        $manager = new class ($handler) implements ForkManagerInterface {
            public array $signals = [];

            private int $killCalls = 0;

            /**
             * @param DefaultSignalHandler $handler
             */
            public function __construct(
                private readonly DefaultSignalHandler $handler
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
            ): void {
                $this->signals[] = $signal;
                ++$this->killCalls;

                if (1 === $this->killCalls) {
                    ($this->handler)($this, Signal::Quit);
                }
            }

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
                return true;
            }

            /**
             * @return bool
             */
            public function isWorker(): bool
            {
                return false;
            }
        };

        $handler($manager, Signal::Interrupt);

        self::assertSame([Signal::Terminate, Signal::Kill], $manager->signals);
    }
}
