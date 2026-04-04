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

namespace FastForward\Fork\Manager;

use FastForward\Fork\Exception\InvalidArgumentException;
use FastForward\Fork\Exception\LogicException;
use FastForward\Fork\Exception\RuntimeException;
use FastForward\Fork\Signal\DefaultSignalHandler;
use FastForward\Fork\Signal\Signal;
use FastForward\Fork\Signal\SignalHandlerInterface;
use FastForward\Fork\Worker\Worker;
use FastForward\Fork\Worker\WorkerGroup;
use FastForward\Fork\Worker\WorkerGroupInterface;
use FastForward\Fork\Worker\WorkerInterface;
use FastForward\Fork\Worker\WorkerState;
use Psr\Log\LoggerInterface;

use function getmypid;
use function usleep;

/**
 * Coordinates the lifecycle of worker processes using process forking.
 *
 * This class acts as the central orchestrator responsible for:
 * - Creating worker processes
 * - Managing their lifecycle and state
 * - Handling inter-process communication via streams
 * - Propagating and handling system signals
 *
 * Implementations MUST ensure that process control functions are available.
 * The manager SHALL operate as a master process and MUST NOT be reused inside worker processes.
 */
final class ForkManager implements ForkManagerInterface
{
    /**
     * POSIX error code for interrupted system calls.
     */
    private const int INTERRUPTED_SYSTEM_CALL_ERROR = 4;

    /**
     * POSIX error code for missing child processes.
     */
    private const int NO_CHILD_PROCESS_ERROR = 10;

    /**
     * Stores all worker instances indexed by their process ID (PID).
     *
     * Each worker instance MUST be uniquely identifiable by its PID.
     *
     * @var array<int, Worker>
     */
    private array $workersByPid = [];

    /**
     * Stores runtime state objects associated with each worker.
     *
     * Each state instance SHALL reflect the current lifecycle status of a worker.
     *
     * @var array<int, WorkerState>
     */
    private array $statesByPid = [];

    /**
     * PID of the master process that instantiated this manager.
     *
     * This value MUST remain immutable after initialization.
     */
    private readonly int $masterPid;

    /**
     * Initializes the manager, validates environment support, and registers signal handlers.
     *
     * The implementation MUST verify that all required extensions are available.
     * If the environment does not support forking, a RuntimeException MUST be thrown.
     *
     * @param SignalHandlerInterface $signalHandler handler responsible for reacting to system signals
     * @param ?LoggerInterface $logger logger used for lifecycle and output events
     *
     * @throws RuntimeException if the environment does not support process forking
     */
    public function __construct(
        private readonly SignalHandlerInterface $signalHandler = new DefaultSignalHandler(),
        private readonly ?LoggerInterface $logger = null,
    ) {
        // @codeCoverageIgnoreStart
        if (! $this->isSupported()) {
            throw RuntimeException::forUnsupportedForking();
        }

        // @codeCoverageIgnoreEnd

        $this->masterPid = $this->detectPid();
        $this->registerSignalHandlers();
    }

    /**
     * Determines whether the current environment supports process forking.
     *
     * All required PHP functions MUST be available for safe execution.
     *
     * @return bool true if forking is supported; otherwise false
     */
    public function isSupported(): bool
    {
        return \function_exists('pcntl_async_signals')
            && \function_exists('pcntl_fork')
            && \function_exists('pcntl_signal')
            && \function_exists('pcntl_waitpid')
            && \function_exists('posix_getpid')
            && \function_exists('posix_kill')
            && \function_exists('stream_socket_pair')
            && \function_exists('stream_select');
    }

    /**
     * Forks one or more worker processes.
     *
     * The manager MUST NOT allow forking from within a worker process.
     * The number of workers MUST be greater than zero.
     *
     * In case of failure during creation, all previously created workers SHALL be terminated.
     *
     * @param callable $workerCallback callback executed inside each worker process
     * @param int $workerCount number of workers to spawn
     *
     * @return WorkerGroupInterface a group containing all created workers
     *
     * @throws LogicException if called from a worker process
     * @throws InvalidArgumentException if worker count is invalid
     * @throws RuntimeException if worker creation fails
     */
    public function fork(callable $workerCallback, int $workerCount = 1): WorkerGroupInterface
    {
        if ($this->isWorker()) {
            throw LogicException::forForkFromWorkerProcess();
        }

        if ($workerCount < 1) {
            throw InvalidArgumentException::forNonPositiveWorkerCount($workerCount);
        }

        $workers = [];

        try {
            for ($slot = 0; $slot < $workerCount; ++$slot) {
                $state = WorkerState::create();

                $worker = new Worker(
                    manager: $this,
                    state: $state,
                    callback: $workerCallback,
                    logger: $this->logger,
                );

                $workers[$worker->getPid()] = $worker;
                $this->workersByPid[$worker->getPid()] = $worker;
                $this->statesByPid[$worker->getPid()] = $state;
            }

            // @codeCoverageIgnoreStart
        } catch (RuntimeException $runtimeException) {
            $this->killWorkers($workers, Signal::Terminate);
            $this->waitOnWorkers($workers);

            throw $runtimeException;
        }

        // @codeCoverageIgnoreEnd

        return new WorkerGroup($this, ...$workers);
    }

    /**
     * Waits for one or more workers or worker groups to finish execution.
     *
     * If no workers are provided, the manager SHALL wait for all managed workers.
     *
     * @param WorkerInterface|WorkerGroupInterface ...$workers Workers or groups to wait for.
     */
    public function wait(WorkerInterface|WorkerGroupInterface ...$workers): void
    {
        $this->waitOnWorkers($this->resolveWorkers(...$workers));
    }

    /**
     * Sends a signal to one or more workers.
     *
     * If no workers are provided, the signal SHALL be sent to all managed workers.
     *
     * @param Signal $signal signal to send (default: SIGTERM)
     * @param WorkerInterface|WorkerGroupInterface ...$workers Target workers or groups.
     */
    public function kill(
        Signal $signal = Signal::Terminate,
        WorkerInterface|WorkerGroupInterface ...$workers,
    ): void {
        $this->killWorkers($this->resolveWorkers(...$workers), $signal);
    }

    /**
     * Retrieves the PID of the master process.
     *
     * @return int the master process identifier
     */
    public function getMasterPid(): int
    {
        return $this->masterPid;
    }

    /**
     * Determines whether the current process is the master process.
     *
     * @return bool true if master; otherwise false
     */
    public function isMaster(): bool
    {
        return $this->detectPid() === $this->masterPid;
    }

    /**
     * Determines whether the current process is a worker process.
     *
     * @return bool true if worker; otherwise false
     */
    public function isWorker(): bool
    {
        return ! $this->isMaster();
    }

    /**
     * Registers signal handlers for all configured signals.
     *
     * The implementation MUST enable asynchronous signal handling.
     */
    private function registerSignalHandlers(): void
    {
        pcntl_async_signals(true);

        foreach ($this->signalHandler->signals() as $signal) {
            pcntl_signal($signal->value, function (int $nativeSignal) use ($signal): void {
                ($this->signalHandler)(
                    $this,
                    Signal::tryFrom($nativeSignal) ?? $signal,
                );
            });
        }
    }

    /**
     * Waits for the provided workers and processes their output streams.
     *
     * The method SHALL continuously monitor worker state and output until all workers terminate.
     *
     * @param array<int, Worker> $workers workers to monitor
     */
    private function waitOnWorkers(array $workers): void
    {
        if ([] === $workers) {
            return;
        }

        $targetPids = array_fill_keys(array_keys($workers), true);

        if ($this->isWorker() && isset($targetPids[$this->detectPid()])) {
            throw LogicException::forWorkerWaitingOnItself($this->detectPid());
        }

        while ($this->hasRunningTargets($targetPids)) {
            $this->drainRunningWorkerOutputs();
            $this->collectExitedWorkers();

            if (! $this->hasRunningTargets($targetPids)) {
                break;
            }

            $readableStreams = [];
            $streamMap = [];

            foreach ($this->statesByPid as $state) {
                if (! $state->isRunning()) {
                    continue;
                }

                foreach ($state->getReadableStreams() as $stream) {
                    $readableStreams[] = $stream;
                    $streamMap[(int) $stream] = $state;
                }
            }

            if ([] === $readableStreams) {
                usleep(100_000);

                continue;
            }

            $selectedStreams = $readableStreams;
            $write = null;
            $except = null;
            $selected = @stream_select($selectedStreams, $write, $except, 0, 200_000);

            // @codeCoverageIgnoreStart
            if (false === $selected) {
                continue;
            }

            // @codeCoverageIgnoreEnd

            if ($selected > 0) {
                $selectedByState = [];

                foreach ($selectedStreams as $stream) {
                    $state = $streamMap[(int) $stream] ?? null;

                    if (! $state instanceof WorkerState) {
                        continue;
                    }

                    $selectedByState[$state->getPid()][] = $stream;
                }

                foreach ($selectedByState as $pid => $streams) {
                    $this->statesByPid[$pid]?->drainOutput(readableStreams: $streams, logger: $this->logger);
                }
            }

            $this->collectExitedWorkers();
        }

        $this->drainRunningWorkerOutputs();
    }

    /**
     * Sends a signal to all provided workers.
     *
     * @param array<int, Worker> $workers workers to signal
     * @param Signal $signal signal to send
     */
    private function killWorkers(array $workers, Signal $signal): void
    {
        foreach ($workers as $worker) {
            $worker->kill($signal);
        }
    }

    /**
     * Detects the current process ID (PID).
     *
     * The implementation SHOULD prefer POSIX functions when available.
     * If detection fails, a RuntimeException MUST be thrown.
     *
     * @return int the current process identifier
     *
     * @throws RuntimeException if the PID cannot be determined
     */
    private function detectPid(): int
    {
        if (\function_exists('posix_getpid')) {
            return posix_getpid();
        }

        // @codeCoverageIgnoreStart
        $pid = getmypid();

        if (! \is_int($pid) || $pid < 1) {
            throw RuntimeException::forUndetectableProcessIdentifier();
        }

        return $pid;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Resolves worker and group inputs into a unique set of workers.
     *
     * The method MUST validate ownership of each worker.
     *
     * @param WorkerInterface|WorkerGroupInterface ...$workers Targets to resolve.
     *
     * @return array<int, Worker>
     *
     * @throws InvalidArgumentException if a worker does not belong to this manager
     */
    private function resolveWorkers(WorkerInterface|WorkerGroupInterface ...$workers): array
    {
        if ([] === $workers) {
            return $this->workersByPid;
        }

        $resolvedWorkers = [];

        foreach ($workers as $target) {
            if ($target instanceof WorkerGroupInterface) {
                if ($target->getManager() !== $this) {
                    throw InvalidArgumentException::forForeignWorkerGroup();
                }

                foreach ($target->all() as $worker) {
                    $this->assertWorkerBelongsToManager($worker);
                    $resolvedWorkers[$worker->getPid()] = $worker;
                }

                continue;
            }

            $this->assertWorkerBelongsToManager($target);
            $resolvedWorkers[$target->getPid()] = $target;
        }

        return $resolvedWorkers;
    }

    /**
     * Ensures that a worker belongs to this manager.
     *
     * The method MUST reject unsupported implementations and foreign workers.
     *
     * @param WorkerInterface $worker worker to validate
     *
     * @throws InvalidArgumentException if validation fails
     */
    private function assertWorkerBelongsToManager(WorkerInterface $worker): void
    {
        if (! $worker instanceof Worker) {
            throw InvalidArgumentException::forUnsupportedWorkerImplementation($worker::class);
        }

        if (($this->workersByPid[$worker->getPid()] ?? null) === $worker) {
            return;
        }

        throw InvalidArgumentException::forForeignWorker($worker->getPid());
    }

    /**
     * Checks if any target worker is still running.
     *
     * @param array<int, true> $targetPids target process IDs
     *
     * @return bool true if at least one worker is running
     */
    private function hasRunningTargets(array $targetPids): bool
    {
        foreach (array_keys($targetPids) as $pid) {
            if ($this->statesByPid[$pid]?->isRunning()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Drains output buffers from all running workers.
     *
     * This method SHOULD be called periodically to avoid blocking pipes.
     */
    private function drainRunningWorkerOutputs(): void
    {
        foreach ($this->statesByPid as $state) {
            if (! $state->isRunning()) {
                continue;
            }

            $state->drainOutput(logger: $this->logger);
        }
    }

    /**
     * Collects terminated child processes.
     *
     * The implementation MUST handle interrupted system calls and absence of child processes.
     *
     * @throws RuntimeException if waiting fails unexpectedly
     */
    private function collectExitedWorkers(): void
    {
        while (true) {
            $status = 0;
            $pid = pcntl_waitpid(-1, $status, \WNOHANG);

            if ($pid > 0) {
                $state = $this->statesByPid[$pid] ?? null;
                if (! $state instanceof WorkerState) {
                    continue;
                }

                if (! $state->isRunning()) {
                    continue;
                }

                $state->markTerminated($status, $this->logger);

                continue;
            }

            if (0 === $pid) {
                return;
            }

            $error = pcntl_get_last_error();

            // @codeCoverageIgnoreStart
            if ($this->isInterruptedWait($error)) {
                continue;
            }

            // @codeCoverageIgnoreEnd

            if ($this->isNoChildError($error)) {
                foreach ($this->statesByPid as $state) {
                    if ($state->isRunning()) {
                        $state->markDetached($this->logger);
                    }
                }

                return;
            }

            // @codeCoverageIgnoreStart
            throw RuntimeException::forWorkersWaitFailure(pcntl_strerror($error));
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Determines if a wait error was caused by an interrupted system call.
     *
     * @param int $error error code
     *
     * @return bool true if interrupted; otherwise false
     */
    private function isInterruptedWait(int $error): bool
    {
        return (\defined('PCNTL_EINTR') ? \PCNTL_EINTR : self::INTERRUPTED_SYSTEM_CALL_ERROR) === $error;
    }

    /**
     * Determines if there are no remaining child processes.
     *
     * @param int $error error code
     *
     * @return bool true if no child processes remain
     */
    private function isNoChildError(int $error): bool
    {
        return (\defined('PCNTL_ECHILD') ? \PCNTL_ECHILD : self::NO_CHILD_PROCESS_ERROR) === $error;
    }
}
