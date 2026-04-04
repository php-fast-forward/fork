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

/**
 * Defines the contract for a single worker process.
 *
 * Implementations MUST represent a process created and managed by a fork manager.
 * A worker SHALL expose its lifecycle state, execution result, and communication
 * channels (output and error output).
 *
 * Consumers MAY interact with a worker to observe its execution, wait for its
 * completion, or send signals to control its lifecycle.
 *
 * Implementations SHOULD ensure that state access is safe and consistent across
 * process boundaries.
 *
 * The key words "MUST", "MUST NOT", "SHALL", "SHALL NOT", "SHOULD",
 * "SHOULD NOT", "RECOMMENDED", "MAY", and "OPTIONAL" are to be interpreted as
 * described in RFC 2119.
 */
interface WorkerInterface
{
    /**
     * Returns the process identifier (PID) of this worker.
     *
     * The PID MUST uniquely identify the worker process within the operating system.
     *
     * @return int the worker process identifier
     */
    public function getPid(): int;

    /**
     * Indicates whether this worker is still running.
     *
     * Implementations MUST return true while the worker process is active and
     * has not yet terminated.
     *
     * @return bool true if the worker is running; otherwise false
     */
    public function isRunning(): bool;

    /**
     * Returns the raw status reported by the operating system, when available.
     *
     * Implementations MAY return null if the worker has not yet terminated or if
     * the status is not available.
     *
     * @return int|null the raw process status or null if unavailable
     */
    public function getStatus(): ?int;

    /**
     * Returns the worker exit code when it exits normally.
     *
     * Implementations MUST return a value between 0 and 255 when the worker
     * terminates normally. If the worker has not exited or was terminated by a
     * signal, null SHALL be returned.
     *
     * @return int|null the exit code or null if not applicable
     */
    public function getExitCode(): ?int;

    /**
     * Returns the signal that terminated the worker.
     *
     * Implementations MUST return the corresponding signal when the worker was
     * terminated by a signal. If the worker exited normally or has not yet
     * terminated, null SHALL be returned.
     *
     * @return Signal|null the terminating signal or null if not applicable
     */
    public function getTerminationSignal(): ?Signal;

    /**
     * Returns the captured standard output produced by this worker.
     *
     * Implementations MAY return partial output while the worker is still
     * running. Once the worker has terminated, the returned output SHOULD be
     * complete.
     *
     * @return string the captured output
     */
    public function getOutput(): string;

    /**
     * Returns the captured error output produced by this worker.
     *
     * Implementations MAY return partial error output while the worker is still
     * running. Once the worker has terminated, the returned output SHOULD be
     * complete.
     *
     * @return string the captured error output
     */
    public function getErrorOutput(): string;

    /**
     * Waits until this worker has finished execution.
     *
     * Implementations MUST block the caller until the worker process has
     * terminated or has been fully reconciled by the manager.
     *
     * @return void
     */
    public function wait(): void;

    /**
     * Sends a signal to this worker.
     *
     * Implementations MUST attempt to deliver the provided signal to the worker
     * process. If the worker is no longer running, the implementation MAY ignore
     * the request.
     *
     * @param Signal $signal the signal to send to the worker
     *
     * @return void
     */
    public function kill(Signal $signal = Signal::Terminate): void;
}
