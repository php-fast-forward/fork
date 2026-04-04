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

use FastForward\Fork\Signal\Signal;
use Psr\Log\LoggerInterface;

use function pcntl_wifexited;
use function pcntl_wifsignaled;
use function pcntl_wexitstatus;
use function pcntl_wtermsig;

/**
 * Stores the mutable runtime state associated with a worker process.
 *
 * This component is responsible for tracking and synchronizing the lifecycle
 * and execution metadata of a worker across parent and child processes.
 *
 * It encapsulates:
 * - Process identification (PID)
 * - Execution state (running, terminated, detached)
 * - Exit metadata (status, exit code, termination signal)
 * - Output streaming via an internal transport
 *
 * Instances of this class MUST be created through the factory method to ensure
 * proper initialization of internal transport resources.
 *
 * @internal
 */
final class WorkerState
{
    /**
     * Stores the worker PID once either side of the fork has been activated.
     *
     * A value of 0 indicates that the process has not yet been initialized.
     */
    private int $pid = 0;

    /**
     * Indicates whether the worker is still considered running.
     *
     * This flag SHALL transition to false once the worker terminates or becomes detached.
     */
    private bool $running = true;

    /**
     * Stores the raw status value returned by the operating system.
     *
     * This value MAY be null until the worker has terminated.
     */
    private ?int $status = null;

    /**
     * Stores the normalized exit code when the worker exits normally.
     *
     * This value SHALL be null if the worker has not exited or was terminated by a signal.
     */
    private ?int $exitCode = null;

    /**
     * Stores the signal that terminated the worker, when applicable.
     *
     * This value SHALL be null if the worker exited normally or has not yet terminated.
     */
    private ?Signal $terminationSignal = null;

    /**
     * Initializes the worker state with its output transport.
     *
     * @param WorkerOutputTransport $outputTransport transport used to stream output from the worker process
     */
    private function __construct(
        private readonly WorkerOutputTransport $outputTransport,
    ) {}

    /**
     * Creates a fresh runtime state instance with a new output transport.
     *
     * The method MUST ensure that all required resources are properly initialized.
     *
     * @return self a new worker state instance
     */
    public static function create(): self
    {
        return new self(WorkerOutputTransport::create());
    }

    /**
     * Activates the parent-side view of the worker state.
     *
     * The parent process MUST set the PID and initialize its side of the transport.
     *
     * @param int $pid the PID of the forked worker process
     */
    public function activateParent(int $pid): void
    {
        $this->pid = $pid;
        $this->outputTransport->activateParentSide();
    }

    /**
     * Activates the child-side view of the worker state.
     *
     * The child process MUST set its PID and release parent-side transport resources.
     *
     * @param int $pid the PID of the current process
     */
    public function activateChild(int $pid): void
    {
        $this->pid = $pid;
        $this->outputTransport->activateChildSide();
    }

    /**
     * Returns the PID currently associated with the worker state.
     *
     * @return int the worker process identifier
     */
    public function getPid(): int
    {
        return $this->pid;
    }

    /**
     * Indicates whether the worker is still running.
     *
     * @return bool true if the worker is active; otherwise false
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Returns the raw wait status when available.
     *
     * @return int|null the raw status or null if not yet available
     */
    public function getStatus(): ?int
    {
        return $this->status;
    }

    /**
     * Returns the normalized exit code when the worker exited normally.
     *
     * @return int|null the exit code or null if not applicable
     */
    public function getExitCode(): ?int
    {
        return $this->exitCode;
    }

    /**
     * Returns the terminating signal when the worker exited due to a signal.
     *
     * @return Signal|null the terminating signal or null if not applicable
     */
    public function getTerminationSignal(): ?Signal
    {
        return $this->terminationSignal;
    }

    /**
     * Returns the stdout accumulated for the worker so far.
     *
     * @return string captured standard output
     */
    public function getOutput(): string
    {
        return $this->outputTransport->getOutput();
    }

    /**
     * Returns the error output accumulated for the worker so far.
     *
     * @return string captured error output
     */
    public function getErrorOutput(): string
    {
        return $this->outputTransport->getErrorOutput();
    }

    /**
     * Returns the readable streams that still belong to a running worker.
     *
     * If the worker is no longer running, an empty array SHALL be returned.
     *
     * @return array<int, resource> readable streams
     */
    public function getReadableStreams(): array
    {
        if (! $this->running) {
            return [];
        }

        return $this->outputTransport->getReadableStreams();
    }

    /**
     * Drains any readable output from the worker transport.
     *
     * The method MAY operate on a subset of streams if provided and SHOULD be
     * invoked periodically to avoid buffer saturation.
     *
     * @param array<int, resource> $readableStreams optional subset of readable streams
     * @param bool $final whether this is the final drain operation
     * @param ?LoggerInterface $logger logger used for chunk-level output events
     */
    public function drainOutput(
        array $readableStreams = [],
        bool $final = false,
        ?LoggerInterface $logger = null,
    ): void {
        $this->outputTransport->drain(
            workerPid: $this->pid,
            readableStreams: $readableStreams,
            final: $final,
            logger: $logger,
        );
    }

    /**
     * Writes a stdout chunk into the transport connected to the parent process.
     *
     * @param string $chunk output chunk
     */
    public function writeOutput(string $chunk): void
    {
        $this->outputTransport->writeOutput($chunk);
    }

    /**
     * Writes an error-output chunk into the transport connected to the parent process.
     *
     * @param string $chunk error output chunk
     */
    public function writeErrorOutput(string $chunk): void
    {
        $this->outputTransport->writeErrorOutput($chunk);
    }

    /**
     * Closes the child-side transport resources.
     *
     * This method SHOULD be called once the worker has finished execution.
     */
    public function closeChildSide(): void
    {
        $this->outputTransport->closeChildSide();
    }

    /**
     * Marks the worker as terminated and captures its final exit metadata.
     *
     * The method MUST update all relevant lifecycle fields and perform a final
     * output drain before logging termination details.
     *
     * @param int $status raw process status returned by the operating system
     * @param ?LoggerInterface $logger logger used for lifecycle events
     */
    public function markTerminated(int $status, ?LoggerInterface $logger = null): void
    {
        $this->running = false;
        $this->status = $status;
        $this->exitCode = null;
        $this->terminationSignal = null;

        if (pcntl_wifexited($status)) {
            $this->exitCode = pcntl_wexitstatus($status);
        } elseif (pcntl_wifsignaled($status)) {
            $this->terminationSignal = Signal::tryFrom(pcntl_wtermsig($status));
        }

        $this->drainOutput(final: true, logger: $logger);

        $logger?->info('Worker process terminated.', [
            'worker_pid' => $this->pid,
            'exit_code' => $this->exitCode,
            'termination_signal' => $this->terminationSignal?->name,
        ]);
    }

    /**
     * Marks the worker as detached when it can no longer be waited on by the parent.
     *
     * This condition MAY occur if the process exits outside of the manager's control.
     *
     * @param ?LoggerInterface $logger logger used for lifecycle events
     */
    public function markDetached(?LoggerInterface $logger = null): void
    {
        $this->running = false;
        $this->drainOutput(final: true, logger: $logger);

        $logger?->warning('Worker process detached before wait completion.', [
            'worker_pid' => $this->pid,
        ]);
    }
}
