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
use FastForward\Fork\Signal\DefaultSignalHandler;
use FastForward\Fork\Signal\Signal;
use FastForward\Fork\Worker\WorkerInterface;

require __DIR__ . '/bootstrap.php';

$console = new ExampleConsole();

$console->title(
    '09 Default signal handler',
    'Send a signal to the master process and let DefaultSignalHandler propagate it to every worker.',
);

$manager = new ForkManager(signalHandler: new DefaultSignalHandler(exitOnSignal: false));

$group = $manager->fork(
    static function (WorkerInterface $worker): int {
        echo sprintf("worker %d ready\n", $worker->getPid());

        while (true) {
            echo sprintf("worker %d heartbeat\n", $worker->getPid());
            usleep(150_000);
        }
    },
    2,
);

$workersReady = ExampleRuntime::waitUntil(
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

if (! $workersReady) {
    throw new RuntimeException('Expected the workers to become ready before signaling the master.');
}

$console->line(sprintf('Master PID: %d', $manager->getMasterPid()));
$console->line('Sending SIGTERM to the master process.');

posix_kill($manager->getMasterPid(), Signal::Terminate->value);

$console->printGroup('Workers after signal propagation', $group);
