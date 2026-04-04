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

use FastForward\Fork\Manager\ForkManager;
use FastForward\Fork\Signal\DefaultSignalHandler;
use FastForward\Fork\Worker\WorkerGroupInterface;
use FastForward\Fork\Worker\WorkerInterface;
use RuntimeException;

use function array_filter;
use function array_map;
use function array_values;
use function json_encode;
use function str_contains;

/**
 * Encapsulates the smoke-test example used to verify the core library flow.
 */
final readonly class SmokeVerifier
{
    /**
     * @param ExampleConsole $console
     */
    public function __construct(
        private ExampleConsole $console = new ExampleConsole(),
    ) {}

    /**
     * Runs the smoke test and throws when any expectation is not met.
     */
    public function run(): void
    {
        $this->console->title(
            '10 Verify library behavior',
            'Run a deterministic smoke test that checks partial output, final output, exit codes, and worker state transitions.',
        );

        $manager = new ForkManager(signalHandler: new DefaultSignalHandler(exitOnSignal: false));

        $group = $manager->fork(
            static function (WorkerInterface $worker): int {
                echo \sprintf("worker-%d-start\n", $worker->getPid());
                trigger_error(\sprintf('worker-%d-warning', $worker->getPid()), \E_USER_WARNING);
                usleep(30_000);
                echo \sprintf("worker-%d-stop\n", $worker->getPid());

                return 0;
            },
            3,
        );

        $partialReady = ExampleRuntime::waitUntil(
            fn(): bool => $this->hasPartialOutput($group),
            timeoutSeconds: 1.0,
        );

        $this->assert(
            $partialReady,
            'Expected partial stdout and stderr to be available before worker completion.',
        );

        $manager->wait();

        $this->assert(3 === $group->count(), \sprintf('Unexpected worker count: %d', $group->count()));

        $exitCodes = array_values(array_map(
            static fn(WorkerInterface $worker): ?int => $worker->getExitCode(),
            $group->all(),
        ));

        $this->assert($exitCodes === [0, 0, 0], \sprintf('Unexpected exit codes: %s', $this->json($exitCodes)));

        foreach ($this->outputs($group) as $output) {
            $this->assert(
                str_contains($output, '-start') && str_contains($output, '-stop'),
                \sprintf('Unexpected stdout payload: %s', $this->json($output)),
            );
        }

        foreach ($this->errorOutputs($group) as $output) {
            $this->assert(
                str_contains($output, 'worker-') && str_contains($output, 'PHP error'),
                \sprintf('Unexpected stderr payload: %s', $this->json($output)),
            );
        }

        $this->assert([] === $group->getRunning(), 'Expected all workers to be stopped.');
        $this->assert(3 === \count($group->getStopped()), 'Expected all workers to be listed as stopped.');

        $this->console->line(\sprintf(
            'verification-passed master=%d workers=%d exit-codes=%s',
            $manager->getMasterPid(),
            $group->count(),
            $this->json($exitCodes),
        ));

        $this->console->printGroup('Final worker snapshots', $group);
    }

    /**
     * Indicates whether partial stdout and stderr are already visible for every worker.
     *
     * @param WorkerGroupInterface $group
     */
    private function hasPartialOutput(WorkerGroupInterface $group): bool
    {
        $partialOutputs = $this->outputs($group);
        $partialErrorOutputs = $this->errorOutputs($group);

        return 3 === \count(array_filter(
            $partialOutputs,
            static fn(string $output): bool => str_contains($output, '-start'),
        ))
            && 3 === \count(array_filter(
                $partialErrorOutputs,
                static fn(string $output): bool => str_contains($output, 'worker-'),
            ));
    }

    /**
     * @param WorkerGroupInterface $group
     *
     * @return array<int, string>
     */
    private function outputs(WorkerGroupInterface $group): array
    {
        return array_values(array_map(
            static fn(WorkerInterface $worker): string => $worker->getOutput(),
            $group->all(),
        ));
    }

    /**
     * @param WorkerGroupInterface $group
     *
     * @return array<int, string>
     */
    private function errorOutputs(WorkerGroupInterface $group): array
    {
        return array_values(array_map(
            static fn(WorkerInterface $worker): string => $worker->getErrorOutput(),
            $group->all(),
        ));
    }

    /**
     * Throws when the provided condition is false.
     *
     * @param bool $condition
     * @param string $message
     */
    private function assert(bool $condition, string $message): void
    {
        if ($condition) {
            return;
        }

        throw new RuntimeException($message);
    }

    /**
     * Encodes a value as JSON for error messages.
     *
     * @param mixed $value
     */
    private function json(mixed $value): string
    {
        return json_encode($value, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
    }
}
