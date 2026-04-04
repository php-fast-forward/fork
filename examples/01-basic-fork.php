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
use FastForward\Fork\Manager\ForkManager;
use FastForward\Fork\Worker\WorkerInterface;

require __DIR__ . '/bootstrap.php';

$console = new ExampleConsole();

$console->title(
    '01 Basic fork',
    'Create a worker group, wait for it once, and inspect the final worker snapshots.',
);

$manager = new ForkManager();

$group = $manager->fork(
    static function (WorkerInterface $worker): int {
        echo sprintf("worker %d started\n", $worker->getPid());
        usleep(150_000);
        echo sprintf("worker %d finished\n", $worker->getPid());

        return 0;
    },
    3,
);

$console->line(sprintf('Master PID: %d', $manager->getMasterPid()));
$console->line('Waiting for the whole group with $group->wait().');

$group->wait();

$console->printGroup('Final worker snapshots', $group);
