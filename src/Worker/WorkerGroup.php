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

use ArrayIterator;
use FastForward\Fork\Manager\ForkManagerInterface;
use FastForward\Fork\Signal\Signal;
use Traversable;

use function array_filter;

/**
 * Provides an immutable view over a collection of workers created by the same manager.
 *
 * This class acts as a value object representing a group of workers and offers
 * convenience methods for querying and interacting with them collectively.
 *
 * Instances of this class MUST be treated as immutable. Consumers SHOULD NOT
 * attempt to modify the internal worker collection after instantiation.
 */
final class WorkerGroup implements WorkerGroupInterface
{
    /**
     * Stores the workers tracked by this group.
     *
     * @var array<int, WorkerInterface>
     */
    private array $workers = [];

    /**
     * Initializes the worker group.
     *
     * @param ForkManagerInterface $manager manager that created the grouped workers
     * @param WorkerInterface ...$workers Workers exposed through this immutable group.
     */
    public function __construct(
        private readonly ForkManagerInterface $manager,
        WorkerInterface ...$workers,
    ) {
        foreach ($workers as $worker) {
            $this->workers[$worker->getPid()] = $worker;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getManager(): ForkManagerInterface
    {
        return $this->manager;
    }

    /**
     * {@inheritDoc}
     *
     * @return array<int, WorkerInterface>
     */
    public function all(): array
    {
        return $this->workers;
    }

    /**
     * {@inheritDoc}
     */
    public function get(int $pid): ?WorkerInterface
    {
        return $this->workers[$pid] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function wait(): void
    {
        $this->manager->wait($this);
    }

    /**
     * {@inheritDoc}
     */
    public function kill(Signal $signal = Signal::Terminate): void
    {
        $this->manager->kill($signal, $this);
    }

    /**
     * {@inheritDoc}
     *
     * @return array<int, WorkerInterface>
     */
    public function getRunning(): array
    {
        return array_filter($this->workers, static fn(WorkerInterface $worker): bool => $worker->isRunning());
    }

    /**
     * {@inheritDoc}
     *
     * @return array<int, WorkerInterface>
     */
    public function getStopped(): array
    {
        return array_filter($this->workers, static fn(WorkerInterface $worker): bool => ! $worker->isRunning());
    }

    /**
     * {@inheritDoc}
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->workers);
    }

    /**
     * {@inheritDoc}
     */
    public function count(): int
    {
        return \count($this->workers);
    }
}
