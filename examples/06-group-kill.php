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
    '06 Group kill',
    'Start long-running workers, stop the entire group with one call, and inspect the termination state.',
);

$manager = new ForkManager();

$group = $manager->fork(
    static function (WorkerInterface $worker): int {
        echo sprintf("worker %d ready\n", $worker->getPid());

        while (true) {
            echo sprintf("worker %d heartbeat\n", $worker->getPid());
            usleep(120_000);
        }
    },
    3,
);

$allWorkersReady = ExampleRuntime::waitUntil(
    static function () use ($group): bool {
        foreach ($group->all() as $worker) {
            if (! str_contains($worker->getOutput(), 'ready')) {
                return false;
            }
        }

        return true;
    },
    timeoutSeconds: 1.0,
);

if (! $allWorkersReady) {
    throw new RuntimeException('Expected every worker to announce readiness before termination.');
}

$console->line('Stopping the entire group with $group->kill().');
$group->kill();
$group->wait();

$console->printGroup('Group after termination', $group);
