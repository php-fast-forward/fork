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

use Countable;
use FastForward\Fork\Manager\ForkManagerInterface;
use FastForward\Fork\Signal\Signal;
use IteratorAggregate;
use Traversable;

/**
 * Defines an immutable group of workers.
 *
 * Implementations MUST represent a read-only collection of workers that were
 * created by the same manager instance. The group SHALL provide consistent
 * access, iteration, and batch operations over the underlying workers.
 *
 * Consumers MAY use this abstraction to coordinate multiple workers as a single
 * unit without managing them individually.
 *
 * @extends IteratorAggregate<int, WorkerInterface>
 */
interface WorkerGroupInterface extends Countable, IteratorAggregate
{
    /**
     * Returns the manager that created this worker group.
     *
     * The returned manager MUST be the same instance responsible for all workers
     * contained in this group.
     *
     * @return ForkManagerInterface the manager associated with this group
     */
    public function getManager(): ForkManagerInterface;

    /**
     * Returns all tracked workers.
     *
     * The returned array MUST contain all workers belonging to this group and
     * SHALL be indexed by their process identifiers (PID).
     *
     * @return array<int, WorkerInterface> all workers in the group
     */
    public function all(): array;

    /**
     * Returns a worker by its PID.
     *
     * If no worker exists for the given PID, the method MUST return null.
     *
     * @param int $pid the process identifier of the worker
     *
     * @return WorkerInterface|null the matching worker or null if not found
     */
    public function get(int $pid): ?WorkerInterface;

    /**
     * Waits until all workers in this group have finished.
     *
     * Implementations MUST block until all workers have terminated or have been
     * fully reconciled by the manager.
     *
     * @return void
     */
    public function wait(): void;

    /**
     * Sends a signal to all workers in this group.
     *
     * Implementations MUST ensure that the signal is delivered only to workers
     * belonging to this group.
     *
     * @param Signal $signal the signal to send to each worker
     *
     * @return void
     */
    public function kill(Signal $signal = Signal::Terminate): void;

    /**
     * Returns the workers that are currently running.
     *
     * Implementations MUST return only workers whose execution has not yet
     * completed.
     *
     * @return array<int, WorkerInterface> running workers
     */
    public function getRunning(): array;

    /**
     * Returns the workers that are no longer running.
     *
     * Implementations MUST return only workers that have already terminated or
     * are no longer active.
     *
     * @return array<int, WorkerInterface> stopped workers
     */
    public function getStopped(): array;

    /**
     * Returns an iterator for the tracked workers.
     *
     * The iterator MUST provide read-only traversal over the worker collection.
     *
     * @return Traversable<int, WorkerInterface> iterator over workers
     */
    public function getIterator(): Traversable;

    /**
     * Returns the number of tracked workers.
     *
     * @return int total number of workers in the group
     */
    public function count(): int;
}
