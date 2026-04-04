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
use FastForward\Fork\Signal\Signal;
use FastForward\Fork\Worker\WorkerInterface;

require __DIR__ . '/bootstrap.php';

$console = new ExampleConsole();

$console->title(
    '07 Targeted manager control',
    'Use $manager->kill() and $manager->wait() with individual workers and worker groups in the same call.',
);

$manager = new ForkManager();

$apiGroup = $manager->fork(
    static function (WorkerInterface $worker): int {
        echo sprintf("api worker %d ready\n", $worker->getPid());

        while (true) {
            echo sprintf("api worker %d heartbeat\n", $worker->getPid());
            usleep(140_000);
        }
    },
    2,
);

$queueGroup = $manager->fork(
    static function (WorkerInterface $worker): int {
        echo sprintf("queue worker %d ready\n", $worker->getPid());

        while (true) {
            echo sprintf("queue worker %d heartbeat\n", $worker->getPid());
            usleep(140_000);
        }
    },
    2,
);

$allReady = ExampleRuntime::waitUntil(
    static function () use ($apiGroup, $queueGroup): bool {
        foreach ([$apiGroup, $queueGroup] as $group) {
            foreach ($group->all() as $worker) {
                if (! str_contains($worker->getOutput(), 'ready')) {
                    return false;
                }
            }
        }

        return true;
    },
    timeoutSeconds: 1.2,
);

if (! $allReady) {
    throw new RuntimeException('Expected both groups to become ready before targeted control.');
}

$queueWorkers = array_values($queueGroup->all());
$selectedQueueWorker = $queueWorkers[0] ?? throw new RuntimeException('Expected a queue worker to select.');
$remainingQueueWorkers = array_slice($queueWorkers, 1);

$console->line(sprintf(
    'Stopping the whole API group plus queue worker %d with $manager->kill().',
    $selectedQueueWorker->getPid(),
));

$manager->kill(Signal::Terminate, $apiGroup, $selectedQueueWorker);
$manager->wait($apiGroup, $selectedQueueWorker);

$console->printGroup('API group after targeted shutdown', $apiGroup);
$console->printWorker('Individually stopped queue worker', $selectedQueueWorker);
$console->printWorkers('Queue workers still running', $queueGroup->getRunning());

$console->line('Stopping the remaining queue workers with targeted manager calls.');
$manager->kill(Signal::Terminate, ...$remainingQueueWorkers);
$manager->wait(...$remainingQueueWorkers);

$console->printGroup('Queue group after all targeted operations', $queueGroup);
