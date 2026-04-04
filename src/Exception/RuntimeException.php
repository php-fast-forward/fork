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

namespace FastForward\Fork\Exception;

/**
 * Represents runtime failures raised while spawning, coordinating, or monitoring
 * worker processes.
 *
 * This exception type is intended for operational failures that occur during
 * process management, transport allocation, PID resolution, or wait handling.
 * Callers MUST treat this exception as an execution-time failure rather than as
 * an indication of invalid API usage. Library internals SHOULD use this
 * exception when an operation is valid in principle but cannot be completed in
 * the current runtime state.
 */
final class RuntimeException extends \RuntimeException implements ForkExceptionInterface
{
    /**
     * Initializes the exception with a descriptive message.
     *
     * This constructor is private to enforce the use of named constructors for specific
     * logic violation scenarios, ensuring that each exception instance is created with
     * a clear and relevant message.
     *
     * @param string $message
     */
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    /**
     * Creates an exception for runtimes that cannot support process forking.
     *
     * The current runtime MUST provide the capabilities required for safe worker
     * process creation. If those capabilities are unavailable, the library SHALL
     * reject the operation by raising this exception.
     *
     * @return self a new instance describing the unsupported runtime
     */
    public static function forUnsupportedForking(): self
    {
        return new self('Process forking is not supported in the current runtime.');
    }

    /**
     * Creates an exception for failed worker fork attempts.
     *
     * Worker creation MUST succeed before the library can continue process
     * orchestration. If the underlying fork operation fails, this exception MUST
     * be thrown to indicate that no valid worker process could be established.
     *
     * @return self a new instance describing the fork failure
     */
    public static function forUnableToForkWorker(): self
    {
        return new self('Unable to fork a new worker process.');
    }

    /**
     * Creates an exception for failures while waiting on a specific worker.
     *
     * Waiting on an individual worker SHOULD complete successfully when the
     * worker is under manager control. If the wait operation fails, the library
     * SHALL raise this exception with contextual error information.
     *
     * @param int $workerPid the process identifier of the worker that could not
     *                       be awaited successfully
     * @param string $error the low-level error message associated with the wait
     *                      failure
     *
     * @return self a new instance describing the worker wait failure
     */
    public static function forWorkerWaitFailure(int $workerPid, string $error): self
    {
        return new self(\sprintf('Unable to wait for worker %d: %s', $workerPid, $error));
    }

    /**
     * Creates an exception for failures while resolving the current PID.
     *
     * The library MUST be able to determine the current process identifier to
     * distinguish master and worker execution contexts correctly. If PID
     * detection fails, continued execution SHALL NOT be considered reliable.
     *
     * @return self a new instance describing the PID detection failure
     */
    public static function forUndetectableProcessIdentifier(): self
    {
        return new self('Unable to detect the current process identifier.');
    }

    /**
     * Creates an exception for failures while allocating worker output transport.
     *
     * Worker output transport resources MUST be allocated before output can be
     * captured or monitored safely. If transport allocation fails, this
     * exception SHALL indicate that the worker communication channel could not be
     * prepared.
     *
     * @return self a new instance describing the output transport allocation failure
     */
    public static function forWorkerOutputAllocationFailure(): self
    {
        return new self('Unable to allocate transport for worker output.');
    }

    /**
     * Creates an exception for failures while waiting on multiple workers.
     *
     * Group wait operations SHOULD reconcile all targeted workers in a
     * predictable manner. If the aggregate wait operation fails, this exception
     * MUST communicate the underlying error so the caller can handle the runtime
     * failure explicitly.
     *
     * @param string $error the low-level error message associated with the group
     *                      wait failure
     *
     * @return self a new instance describing the multi-worker wait failure
     */
    public static function forWorkersWaitFailure(string $error): self
    {
        return new self(\sprintf('Unable to wait for workers: %s', $error));
    }
}
