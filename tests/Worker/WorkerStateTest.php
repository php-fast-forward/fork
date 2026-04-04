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

use FastForward\Fork\Signal\Signal;
use FastForward\Fork\Tests\Support\ReflectsNonPublicMembers;
use FastForward\Fork\Tests\Support\SpyLogger;
use FastForward\Fork\Worker\WorkerState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function pcntl_fork;
use function pcntl_waitpid;
use function posix_kill;
use function usleep;

#[CoversClass(WorkerState::class)]
final class WorkerStateTest extends TestCase
{
    use ReflectsNonPublicMembers;

    /**
     * @return void
     */
    #[Test]
    public function activateParentWillStoreTheProcessIdentifierAndKeepReadableStreams(): void
    {
        $state = WorkerState::create();

        $state->activateParent(123);

        self::assertSame(123, $state->getPid());
        self::assertCount(2, $state->getReadableStreams());
    }

    /**
     * @return void
     */
    #[Test]
    public function activateChildWillStoreTheProcessIdentifierAndHideReadableStreams(): void
    {
        $state = WorkerState::create();

        $state->activateChild(456);

        self::assertSame(456, $state->getPid());
        self::assertSame([], $state->getReadableStreams());
    }

    /**
     * @return void
     */
    #[Test]
    public function itWillDrainOutputAndErrorStreamsIntoItsBuffers(): void
    {
        $state = WorkerState::create();

        $this->setNonPublicProperty($state, 'pid', 42);
        $state->writeOutput('stdout');
        $state->writeErrorOutput('stderr');
        $state->closeChildSide();
        $state->drainOutput(final: true);

        self::assertSame('stdout', $state->getOutput());
        self::assertSame('stderr', $state->getErrorOutput());
    }

    /**
     * @return void
     */
    #[Test]
    public function markTerminatedWillCaptureTheExitCodeAndDrainTheFinalOutput(): void
    {
        $state = WorkerState::create();
        $logger = new SpyLogger();
        $status = $this->forkChildAndCollectExitStatus(7);

        $this->setNonPublicProperty($state, 'pid', 321);
        $state->writeOutput('stdout');
        $state->writeErrorOutput('stderr');
        $state->closeChildSide();
        $state->markTerminated($status, $logger);

        self::assertFalse($state->isRunning());
        self::assertSame($status, $state->getStatus());
        self::assertSame(7, $state->getExitCode());
        self::assertNull($state->getTerminationSignal());
        self::assertSame('stdout', $state->getOutput());
        self::assertSame('stderr', $state->getErrorOutput());
        self::assertTrue($logger->hasInfo('Worker process terminated.'));
    }

    /**
     * @return void
     */
    #[Test]
    public function markTerminatedWillCaptureTheTerminationSignalWhenTheWorkerIsKilled(): void
    {
        $state = WorkerState::create();
        $status = $this->forkChildAndCollectSignalStatus(Signal::Terminate);

        $this->setNonPublicProperty($state, 'pid', 654);
        $state->closeChildSide();
        $state->markTerminated($status);

        self::assertFalse($state->isRunning());
        self::assertSame($status, $state->getStatus());
        self::assertNull($state->getExitCode());
        self::assertSame(Signal::Terminate, $state->getTerminationSignal());
    }

    /**
     * @return void
     */
    #[Test]
    public function markDetachedWillStopTheWorkerAndDrainRemainingOutput(): void
    {
        $state = WorkerState::create();
        $logger = new SpyLogger();

        $this->setNonPublicProperty($state, 'pid', 777);
        $state->writeOutput('stdout');
        $state->closeChildSide();
        $state->markDetached($logger);

        self::assertFalse($state->isRunning());
        self::assertSame([], $state->getReadableStreams());
        self::assertSame('stdout', $state->getOutput());
        self::assertTrue($logger->hasWarning('Worker process detached before wait completion.'));
    }

    /**
     * @param int $exitCode
     *
     * @return int
     */
    private function forkChildAndCollectExitStatus(int $exitCode): int
    {
        $pid = pcntl_fork();

        if (0 === $pid) {
            exit($exitCode);
        }

        $status = 0;
        pcntl_waitpid($pid, $status);

        return $status;
    }

    /**
     * @param Signal $signal
     *
     * @return int
     */
    private function forkChildAndCollectSignalStatus(Signal $signal): int
    {
        $pid = pcntl_fork();

        if (0 === $pid) {
            usleep(500_000);
            exit(0);
        }

        usleep(50_000);
        posix_kill($pid, $signal->value);

        $status = 0;
        pcntl_waitpid($pid, $status);

        return $status;
    }
}
