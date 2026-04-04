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

use FastForward\Fork\Examples\Support\ExampleConsole;
use FastForward\Fork\Examples\Support\ExampleRuntime;
use FastForward\Fork\Manager\ForkManager;
use FastForward\Fork\Worker\WorkerInterface;

require __DIR__ . '/bootstrap.php';

$console = new ExampleConsole();

$console->title(
    '02 Inspect worker group',
    'Observe workers while they are still running and access them by PID.',
);

$manager = new ForkManager();

$group = $manager->fork(
    static function (WorkerInterface $worker): int {
        $delayMilliseconds = match ($worker->getPid() % 3) {
            0 => 250,
            1 => 450,
            default => 650,
        };

        echo sprintf("worker %d sleeping for %dms\n", $worker->getPid(), $delayMilliseconds);
        usleep($delayMilliseconds * 1_000);
        echo sprintf("worker %d finished\n", $worker->getPid());

        return 0;
    },
    3,
);

$firstPid = array_key_first($group->all());

if (! is_int($firstPid)) {
    throw new RuntimeException('Expected at least one worker in the group.');
}

$console->line(sprintf('Initial group size: %d', $group->count()));
$console->line(sprintf('Initial running workers: %d', count($group->getRunning())));
$console->printWorker(
    sprintf('Worker fetched with $group->get(%d)', $firstPid),
    $group->get($firstPid) ?? throw new RuntimeException('Expected the worker fetched by PID to exist.'),
);

$stoppedDetected = ExampleRuntime::waitUntil(
    static fn(): bool => [] !== $group->getStopped(),
    timeoutSeconds: 1.5,
);

if (! $stoppedDetected) {
    throw new RuntimeException('Expected at least one worker to finish during the inspection window.');
}

$console->printWorkers('Workers still running', $group->getRunning());
$console->printWorkers('Workers already stopped', $group->getStopped());

$group->wait();

$console->printGroup('Group after completion', $group);
