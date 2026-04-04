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

namespace FastForward\Fork\Examples\Support;

use FastForward\Fork\Worker\WorkerGroupInterface;
use FastForward\Fork\Worker\WorkerInterface;

use function json_encode;
use function ksort;
use function printf;
use function str_repeat;

/**
 * Provides consistent console output for the examples.
 */
final class ExampleConsole
{
    /**
     * Prints the example title and a short description.
     *
     * @param string $title
     * @param string $description
     */
    public function title(string $title, string $description): void
    {
        $this->separator();
        $this->line($title);
        $this->separator();
        $this->line($description);
    }

    /**
     * Prints a section title.
     *
     * @param string $title
     */
    public function section(string $title): void
    {
        $this->line();
        $this->line(\sprintf('## %s', $title));
    }

    /**
     * Prints a single console line.
     *
     * @param string $message
     */
    public function line(string $message = ''): void
    {
        printf("%s\n", $message);
    }

    /**
     * Prints a concise summary for the provided worker group.
     *
     * @param string $title
     * @param WorkerGroupInterface $group
     */
    public function printGroup(string $title, WorkerGroupInterface $group): void
    {
        $this->section($title);
        $this->line(\sprintf(
            'workers=%d running=%d stopped=%d',
            $group->count(),
            \count($group->getRunning()),
            \count($group->getStopped()),
        ));

        $this->printWorkers('Worker snapshots', $group->all());
    }

    /**
     * Prints one worker snapshot.
     *
     * @param string $title
     * @param WorkerInterface $worker
     */
    public function printWorker(string $title, WorkerInterface $worker): void
    {
        $this->section($title);
        $this->printSnapshot($worker);
    }

    /**
     * Prints multiple worker snapshots.
     *
     * @param array<int, WorkerInterface> $workers
     * @param string $title
     */
    public function printWorkers(string $title, array $workers): void
    {
        $this->section($title);

        if ([] === $workers) {
            $this->line('No workers matched this selection.');

            return;
        }

        foreach ($this->sortWorkersByPid($workers) as $worker) {
            $this->printSnapshot($worker);
        }
    }

    /**
     * Prints an arbitrary value encoded as JSON.
     *
     * @param string $label
     * @param mixed $value
     */
    public function printValue(string $label, mixed $value): void
    {
        $this->section($label);
        $this->line($this->json($value));
    }

    /**
     * Prints a visual separator.
     */
    private function separator(): void
    {
        $this->line(str_repeat('=', 72));
    }

    /**
     * Prints one formatted worker snapshot.
     *
     * @param WorkerInterface $worker
     */
    private function printSnapshot(WorkerInterface $worker): void
    {
        $this->line($this->json([
            'pid' => $worker->getPid(),
            'running' => $worker->isRunning(),
            'status' => $worker->getStatus(),
            'exitCode' => $worker->getExitCode(),
            'terminationSignal' => $worker->getTerminationSignal()?->name,
            'output' => $worker->getOutput(),
            'errorOutput' => $worker->getErrorOutput(),
        ]));
    }

    /**
     * @param array<int, WorkerInterface> $workers
     *
     * @return array<int, WorkerInterface>
     */
    private function sortWorkersByPid(array $workers): array
    {
        $sortedWorkers = [];

        foreach ($workers as $worker) {
            $sortedWorkers[$worker->getPid()] = $worker;
        }

        ksort($sortedWorkers);

        return $sortedWorkers;
    }

    /**
     * Encodes the provided value as pretty-printed JSON.
     *
     * @param mixed $value
     */
    private function json(mixed $value): string
    {
        return json_encode(
            $value,
            \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR,
        );
    }
}
