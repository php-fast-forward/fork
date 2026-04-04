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

namespace FastForward\Fork\Worker;

use Closure;
use FastForward\Fork\Exception\LogicException;
use FastForward\Fork\Exception\RuntimeException;
use FastForward\Fork\Manager\ForkManagerInterface;
use FastForward\Fork\Signal\Signal;
use Psr\Log\LoggerInterface;
use Throwable;

use function error_reporting;
use function getmypid;
use function max;
use function min;
use function ob_end_flush;
use function ob_get_level;
use function ob_start;
use function pcntl_fork;
use function posix_get_last_error;
use function posix_kill;
use function restore_error_handler;
use function set_error_handler;

/**
 * Represents a single forked worker process.
 *
 * This class encapsulates the lifecycle of a worker created via process forking.
 * It is responsible for:
 * - Spawning the child process
 * - Executing the provided callback in the child context
 * - Capturing output and errors
 * - Synchronizing state with the master process
 *
 * Instances of this class MUST be created exclusively by a manager implementation.
 * Consumers SHOULD NOT instantiate this class directly.
 */
final readonly class Worker implements WorkerInterface
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
     * POSIX error code for non-existent processes.
     */
    private const int NO_SUCH_PROCESS_ERROR = 3;

    /**
     * Stores the callback executed inside the child process.
     *
     * The callback MUST accept the current worker instance as its only argument.
     *
     * @var Closure(WorkerInterface):mixed
     */
    private Closure $callback;

    /**
     * Initializes the worker and immediately forks the process.
     *
     * The constructor MUST trigger process forking and configure both parent
     * and child execution contexts accordingly.
     *
     * @param ForkManagerInterface $manager manager responsible for this worker
     * @param WorkerState $state shared mutable state between parent and child
     * @param callable(WorkerInterface): mixed $callback callback executed in the child process
     * @param ?LoggerInterface $logger logger used for lifecycle events
     */
    public function __construct(
        private ForkManagerInterface $manager,
        private WorkerState $state,
        callable $callback,
        private ?LoggerInterface $logger = null,
    ) {
        $this->callback = Closure::fromCallable($callback);
        $this->startForkedWorker();
    }

    /**
     * {@inheritDoc}
     */
    public function getPid(): int
    {
        return $this->state->getPid();
    }

    /**
     * {@inheritDoc}
     */
    public function isRunning(): bool
    {
        $this->pollState();

        return $this->state->isRunning();
    }

    /**
     * {@inheritDoc}
     */
    public function getStatus(): ?int
    {
        $this->pollState();

        return $this->state->getStatus();
    }

    /**
     * {@inheritDoc}
     */
    public function getExitCode(): ?int
    {
        $this->pollState();

        return $this->state->getExitCode();
    }

    /**
     * {@inheritDoc}
     */
    public function getTerminationSignal(): ?Signal
    {
        $this->pollState();

        return $this->state->getTerminationSignal();
    }

    /**
     * {@inheritDoc}
     */
    public function getOutput(): string
    {
        $this->pollState();

        return $this->state->getOutput();
    }

    /**
     * {@inheritDoc}
     */
    public function getErrorOutput(): string
    {
        $this->pollState();

        return $this->state->getErrorOutput();
    }

    /**
     * {@inheritDoc}
     *
     * This method SHALL block until the worker finishes execution.
     * It MUST NOT be invoked from within the same worker process.
     */
    public function wait(): void
    {
        if (! $this->state->isRunning()) {
            return;
        }

        if ($this->isCurrentWorkerProcess()) {
            throw LogicException::forWorkerWaitingOnItself($this->state->getPid());
        }

        $this->manager->wait($this);
    }

    /**
     * {@inheritDoc}
     *
     * This method attempts to send a POSIX signal to the worker process.
     * If the process no longer exists, the worker SHALL be marked as detached.
     */
    public function kill(Signal $signal = Signal::Terminate): void
    {
        if (! $this->state->isRunning()) {
            return;
        }

        if (posix_kill($this->state->getPid(), $signal->value)) {
            return;
        }

        if ($this->isNoSuchProcessError(posix_get_last_error())) {
            $this->state->markDetached($this->logger);
        }
    }

    /**
     * Forks the current process and initializes parent/child execution paths.
     *
     * The parent process SHALL retain control and track the worker.
     * The child process MUST execute the provided callback and terminate.
     *
     * @throws RuntimeException if the fork operation fails
     */
    private function startForkedWorker(): void
    {
        $pid = pcntl_fork();

        // @codeCoverageIgnoreStart
        if (-1 === $pid) {
            throw RuntimeException::forUnableToForkWorker();
        }

        // @codeCoverageIgnoreEnd

        if ($pid > 0) {
            $this->state->activateParent($pid);

            $this->logger?->info('Forked worker process.', [
                'worker_pid' => $pid,
            ]);

            return;
        }

        // @codeCoverageIgnoreStart
        $pid = $this->detectCurrentPid();
        $this->state->activateChild($pid);

        pcntl_async_signals(true);

        $this->logger?->info('Starting forked worker execution.', [
            'worker_pid' => $pid,
        ]);

        exit($this->executeCallback());
        // @codeCoverageIgnoreEnd
    }

    /**
     * Executes the worker callback and normalizes the exit code.
     *
     * Output buffering MUST be used to capture standard output and forward it
     * to the parent process. Errors SHALL be intercepted and written to the
     * worker error output channel.
     *
     * @return int normalized process exit code (0–255)
     */
    private function executeCallback(): int
    {
        $bufferLevel = ob_get_level();

        set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
            if ((error_reporting() & $severity) === 0) {
                return false;
            }

            $this->state->writeErrorOutput(\sprintf(
                "[fast-forward/fork] PHP error %d: %s in %s on line %d\n",
                $severity,
                $message,
                $file,
                $line,
            ));

            return true;
        });

        ob_start(function (string $buffer): string {
            $this->state->writeOutput($buffer);

            return '';
        }, 1);

        try {
            $result = ($this->callback)($this);

            return $this->normalizeExitCode($result);
        } catch (Throwable $throwable) {
            $this->state->writeErrorOutput(\sprintf(
                "[fast-forward/fork] Worker %d failed with %s: %s\n",
                $this->state->getPid(),
                $throwable::class,
                $throwable->getMessage(),
            ));

            return $throwable->getCode() > 0 ? $throwable->getCode() : 255;
        } finally {
            while (ob_get_level() > $bufferLevel) {
                ob_end_flush();
            }

            restore_error_handler();
            $this->state->closeChildSide();
        }
    }

    /**
     * Normalizes a callback result into a valid exit code.
     *
     * Integers MUST be clamped to the range 0–255.
     * A boolean false SHALL be converted to exit code 1.
     * Any other value SHALL result in exit code 0.
     *
     * @param mixed $result callback return value
     *
     * @return int normalized exit code
     */
    private function normalizeExitCode(mixed $result): int
    {
        if (\is_int($result)) {
            return max(0, min(255, $result));
        }

        if (false === $result) {
            return 1;
        }

        return 0;
    }

    /**
     * Performs a non-blocking state synchronization with the worker process.
     *
     * This method MUST only operate in the master process context.
     *
     * @throws RuntimeException if waiting for the worker fails unexpectedly
     */
    private function pollState(): void
    {
        if (! $this->state->isRunning() || $this->isCurrentWorkerProcess()) {
            return;
        }

        $this->state->drainOutput(logger: $this->logger);

        $status = 0;
        $pid = pcntl_waitpid($this->state->getPid(), $status, \WNOHANG);

        if ($pid > 0) {
            $this->state->markTerminated($status, $this->logger);

            return;
        }

        if (0 === $pid) {
            return;
        }

        $error = pcntl_get_last_error();

        // @codeCoverageIgnoreStart
        if ($this->isInterruptedWait($error)) {
            return;
        }

        // @codeCoverageIgnoreEnd

        if ($this->isNoChildError($error)) {
            $this->state->markDetached($this->logger);

            return;
        }

        // @codeCoverageIgnoreStart
        throw RuntimeException::forWorkerWaitFailure($this->state->getPid(), pcntl_strerror($error));
        // @codeCoverageIgnoreEnd
    }

    /**
     * Determines whether the current process corresponds to this worker.
     *
     * @return bool true if executing inside this worker process
     */
    private function isCurrentWorkerProcess(): bool
    {
        return $this->manager->isWorker()
            && $this->detectCurrentPid() === $this->state->getPid();
    }

    /**
     * Detects the current process identifier (PID).
     *
     * @return int the current process ID
     *
     * @throws RuntimeException if the PID cannot be determined
     */
    private function detectCurrentPid(): int
    {
        $pid = getmypid();

        // @codeCoverageIgnoreStart
        if (! \is_int($pid) || $pid < 1) {
            throw RuntimeException::forUndetectableProcessIdentifier();
        }

        // @codeCoverageIgnoreEnd

        return $pid;
    }

    /**
     * Determines whether a wait failure was caused by an interrupted system call.
     *
     * @param int $error error code returned by pcntl
     *
     * @return bool true if interrupted
     */
    private function isInterruptedWait(int $error): bool
    {
        return (\defined('PCNTL_EINTR') ? \PCNTL_EINTR : self::INTERRUPTED_SYSTEM_CALL_ERROR) === $error;
    }

    /**
     * Determines whether no child processes remain.
     *
     * @param int $error error code returned by pcntl
     *
     * @return bool true if no child processes exist
     */
    private function isNoChildError(int $error): bool
    {
        return (\defined('PCNTL_ECHILD') ? \PCNTL_ECHILD : self::NO_CHILD_PROCESS_ERROR) === $error;
    }

    /**
     * Determines whether the process no longer exists.
     *
     * @param int $error error code returned by POSIX APIs
     *
     * @return bool true if the target process is gone
     */
    private function isNoSuchProcessError(int $error): bool
    {
        return (\defined('POSIX_ESRCH') ? \POSIX_ESRCH : self::NO_SUCH_PROCESS_ERROR) === $error;
    }
}
