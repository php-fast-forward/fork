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

use FastForward\Fork\Signal\Signal;
use FastForward\Fork\Worker\WorkerGroupInterface;
use FastForward\Fork\Worker\WorkerInterface;

/**
 * Implementations of this interface MUST provide the orchestration layer responsible
 * for spawning, supervising, signaling, and synchronizing worker processes.
 * Implementations SHOULD ensure predictable lifecycle management and MUST expose
 * enough behavior to distinguish master and worker execution contexts.
 *
 * The key words "MUST", "MUST NOT", "SHALL", "SHALL NOT", "SHOULD", "SHOULD NOT",
 * "RECOMMENDED", "MAY", and "OPTIONAL" in this contract are to be interpreted as
 * described in RFC 2119.
 */
interface ForkManagerInterface
{
    /**
     * Indicates whether the current runtime supports the library requirements.
     *
     * Implementations MUST validate the availability of the runtime capabilities
     * required to manage worker processes correctly. The returned value SHALL
     * reflect whether the execution environment is suitable for safe operation.
     *
     * @return bool true when the runtime environment supports the required process
     *              control features; otherwise, false
     */
    public function isSupported(): bool;

    /**
     * Forks one or more workers for the provided callback and returns them as a group.
     *
     * The callback MUST receive the current worker as its first argument.
     * Implementations MUST create exactly the requested number of workers when the
     * operation succeeds. If the runtime cannot complete the operation safely,
     * implementations SHOULD fail explicitly rather than returning a partial and
     * misleading result.
     *
     * @param callable(WorkerInterface): mixed $workerCallback callback executed
     *                                                         inside each worker
     *                                                         process
     * @param int $workerCount number of workers to create for the callback
     *
     * @return WorkerGroupInterface a group representing the workers created for
     *                              the provided callback
     */
    public function fork(callable $workerCallback, int $workerCount = 1): WorkerGroupInterface;

    /**
     * Waits until all targeted workers finish.
     *
     * When no worker or group is provided, implementations MUST wait for every
     * worker instantiated by this manager. Implementations SHALL block until the
     * targeted workers have exited or have otherwise been fully reconciled by the
     * manager lifecycle.
     *
     * @param WorkerInterface|WorkerGroupInterface ...$workers Workers and groups
     *                                                         to wait for.
     *
     * @return void
     */
    public function wait(WorkerInterface|WorkerGroupInterface ...$workers): void;

    /**
     * Sends a signal to all targeted workers.
     *
     * When no worker or group is provided, implementations MUST signal every
     * worker instantiated by this manager. Implementations SHOULD ensure that the
     * signal is delivered only to workers under their own control and MUST NOT
     * silently target unrelated processes.
     *
     * @param Signal $signal signal to send to each targeted worker
     * @param WorkerInterface|WorkerGroupInterface ...$workers Workers and groups
     *                                                         to signal.
     *
     * @return void
     */
    public function kill(
        Signal $signal = Signal::Terminate,
        WorkerInterface|WorkerGroupInterface ...$workers,
    ): void;

    /**
     * Returns the PID of the master process associated with this manager.
     *
     * The returned process identifier MUST correspond to the process recognized by
     * the implementation as the master orchestration context.
     *
     * @return int the process identifier of the master process
     */
    public function getMasterPid(): int;

    /**
     * Indicates whether the current execution context is the master process.
     *
     * Implementations MUST return true only when the current process matches the
     * master process associated with the manager instance.
     *
     * @return bool true when the current process is the master process; otherwise,
     *              false
     */
    public function isMaster(): bool;

    /**
     * Indicates whether the current execution context is a worker process.
     *
     * Implementations SHOULD treat this result as the logical inverse of the
     * master-process check whenever that model is valid for the implementation.
     *
     * @return bool true when the current process is executing as a worker;
     *              otherwise, false
     */
    public function isWorker(): bool;
}
