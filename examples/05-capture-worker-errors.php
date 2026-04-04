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
    '05 Capture worker errors',
    'Trigger warnings and exceptions inside workers and inspect the captured stderr and exit codes.',
);

$manager = new ForkManager();

$group = $manager->fork(
    static function (WorkerInterface $worker): int {
        echo sprintf("worker %d booted\n", $worker->getPid());

        if (0 === $worker->getPid() % 2) {
            trigger_error(sprintf('worker %d warning', $worker->getPid()), \E_USER_WARNING);

            return 0;
        }

        throw new RuntimeException(sprintf('worker %d exception', $worker->getPid()), 42);
    },
    2,
);

$group->wait();

$console->printGroup('Captured stdout and stderr', $group);
