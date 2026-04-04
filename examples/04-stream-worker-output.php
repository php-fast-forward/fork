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
    '04 Stream worker output',
    'Read partial output while the workers are still running, then inspect the final captured output.',
);

$manager = new ForkManager();

$group = $manager->fork(
    static function (WorkerInterface $worker): int {
        echo sprintf("worker %d step 1\n", $worker->getPid());
        usleep(250_000);
        echo sprintf("worker %d step 2\n", $worker->getPid());

        return 0;
    },
    2,
);

$partialOutputReady = ExampleRuntime::waitUntil(
    static function () use ($group): bool {
        foreach ($group->all() as $worker) {
            if (! str_contains($worker->getOutput(), 'step 1')) {
                return false;
            }
        }

        return true;
    },
    timeoutSeconds: 1.0,
);

if (! $partialOutputReady) {
    throw new RuntimeException('Expected partial output before the workers completed.');
}

$console->printGroup('Output available before completion', $group);

$group->wait();

$console->printGroup('Output after completion', $group);
