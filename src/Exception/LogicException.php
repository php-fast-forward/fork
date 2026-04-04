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
 * Represents logical contract violations detected during worker orchestration.
 *
 * This exception type is intended for scenarios in which the caller invokes the
 * process management API in a way that is incompatible with the expected runtime
 * lifecycle. Such failures indicate an invalid usage pattern rather than an
 * invalid scalar argument value.
 *
 * Callers MUST treat this exception as a signal of incorrect control flow or
 * invalid process-context usage. Library code SHOULD throw this exception only
 * when the operation is structurally invalid for the current execution state.
 */
final class LogicException extends \LogicException implements ForkExceptionInterface
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
     * Creates an exception for attempts to fork from within a worker process.
     *
     * A worker process MUST NOT reuse a manager instance that belongs to a parent
     * process context for creating nested workers. If nested process management is
     * required, the worker SHOULD instantiate a new manager within its own process.
     *
     * @return self a new instance describing the invalid fork attempt
     */
    public static function forForkFromWorkerProcess(): self
    {
        return new self(
            'Forking from a worker process is not supported by this manager instance. '
            . 'Create a new manager inside the worker to manage a nested process tree.',
        );
    }

    /**
     * Creates an exception for attempts by a worker to wait on itself.
     *
     * A worker MUST NOT attempt to block while waiting for its own termination,
     * because such behavior would be logically invalid and would prevent correct
     * lifecycle coordination.
     *
     * @param int $workerPid the process identifier of the worker attempting to wait on itself
     *
     * @return self a new instance describing the self-wait violation
     */
    public static function forWorkerWaitingOnItself(int $workerPid): self
    {
        return new self(\sprintf('Worker %d cannot wait on itself.', $workerPid));
    }
}
