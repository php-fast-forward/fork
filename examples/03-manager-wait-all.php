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
    '03 Manager wait all',
    'Create more than one group, wait for one group explicitly, then use $manager->wait() with no arguments.',
);

$manager = new ForkManager();

$shortGroup = $manager->fork(
    static function (WorkerInterface $worker): int {
        echo sprintf("short worker %d started\n", $worker->getPid());
        usleep(120_000);
        echo sprintf("short worker %d finished\n", $worker->getPid());

        return 0;
    },
    2,
);

$longGroup = $manager->fork(
    static function (WorkerInterface $worker): int {
        echo sprintf("long worker %d started\n", $worker->getPid());
        usleep(350_000);
        echo sprintf("long worker %d finished\n", $worker->getPid());

        return 0;
    },
    2,
);

$console->line('Waiting only for the short group with $shortGroup->wait().');
$shortGroup->wait();

$console->printGroup('Short group after $shortGroup->wait()', $shortGroup);
$console->printWorkers('Long group workers still running', $longGroup->getRunning());

$console->line('Waiting for every remaining worker created by the manager with $manager->wait().');
$manager->wait();

$console->printGroup('Long group after $manager->wait()', $longGroup);
