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
use FastForward\Fork\Examples\Support\ExampleLogger;
use FastForward\Fork\Manager\ForkManager;
use FastForward\Fork\Worker\WorkerInterface;

require __DIR__ . '/bootstrap.php';

$console = new ExampleConsole();

$console->title(
    '08 Logger integration',
    'Inject a PSR-3 logger to observe worker lifecycle and output events in real time.',
);

$manager = new ForkManager(logger: new ExampleLogger());

$group = $manager->fork(
    static function (WorkerInterface $worker): int {
        echo sprintf("worker %d says hello\n", $worker->getPid());
        usleep(80_000);
        echo sprintf("worker %d says goodbye\n", $worker->getPid());

        return 0;
    },
    2,
);

$group->wait();

$console->printGroup('Workers after logged execution', $group);
