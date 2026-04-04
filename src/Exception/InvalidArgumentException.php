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
 * Represents invalid argument failures raised by the fork library.
 *
 * This exception class centralizes all argument validation errors related to
 * worker management and orchestration.
 *
 * Implementations throwing this exception MUST ensure that the provided input
 * violates the expected contract. Consumers of this exception SHOULD treat it
 * as a programming error and MUST NOT rely on it for normal control flow.
 */
final class InvalidArgumentException extends \InvalidArgumentException implements ForkExceptionInterface
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
     * Creates an exception for a non-positive worker count.
     *
     * The worker count MUST be greater than zero. Any value less than or equal to
     * zero SHALL be considered invalid and MUST trigger this exception.
     *
     * @param int $workerCount the invalid worker count provided by the caller
     *
     * @return self a new instance describing the invalid worker count
     */
    public static function forNonPositiveWorkerCount(int $workerCount): self
    {
        return new self(\sprintf('The worker count must be greater than zero, %d given.', $workerCount));
    }

    /**
     * Creates an exception for a worker that is not owned by the manager.
     *
     * A worker MUST belong to the manager instance that operates on it. If a
     * foreign worker is detected, this exception SHALL be thrown.
     *
     * @param int $workerPid the process identifier of the foreign worker
     *
     * @return self a new instance describing the ownership violation
     */
    public static function forForeignWorker(int $workerPid): self
    {
        return new self(\sprintf('Worker %d is not managed by this fork manager.', $workerPid));
    }

    /**
     * Creates an exception for a worker group that belongs to another manager.
     *
     * Worker groups MUST be associated with the same manager instance. Passing a
     * group from a different manager SHALL result in this exception.
     *
     * @return self a new instance describing the invalid worker group ownership
     */
    public static function forForeignWorkerGroup(): self
    {
        return new self('The provided worker group is not managed by this fork manager.');
    }

    /**
     * Creates an exception for a worker implementation unsupported by the manager.
     *
     * The manager MAY restrict supported worker implementations. If an unsupported
     * implementation is provided, this exception MUST be thrown.
     *
     * @param string $className the fully-qualified class name of the unsupported worker
     *
     * @return self a new instance describing the unsupported implementation
     */
    public static function forUnsupportedWorkerImplementation(string $className): self
    {
        return new self(\sprintf(
            'The worker implementation %s is not supported by this fork manager.',
            $className,
        ));
    }
}
