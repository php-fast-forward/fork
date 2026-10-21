<?php

declare(strict_types=1);

require $argv[1] ?? dirname(__DIR__, 4) . '/vendor/autoload.php';

use FastForward\Fork\Manager\ForkManager;
use FastForward\Fork\Worker\WorkerInterface;

foreach (['pcntl_fork', 'pcntl_signal', 'pcntl_waitpid', 'posix_getpid', 'posix_kill'] as $function) {
    if (!function_exists($function)) {
        throw new RuntimeException('Fork example requires PCNTL/POSIX CLI support.');
    }
}
$manager = new ForkManager();
$group = $manager->fork(static function (WorkerInterface $worker): int {
    echo 'worker-ready';
    return 0;
}, 1);
$group->wait();
foreach ($group as $worker) {
    if ($worker->getExitCode() !== 0 || $worker->getOutput() !== 'worker-ready') {
        throw new RuntimeException('Worker output or exit code differs.');
    }
}
$failed = $manager->fork(static function (): never {
    throw new RuntimeException('worker fixture failure');
});
$failed->wait();
foreach ($failed as $worker) {
    if ($worker->getExitCode() !== 255 || !str_contains($worker->getErrorOutput(), 'worker fixture failure')) {
        throw new RuntimeException('Worker failure was not observable.');
    }
}
try {
    $manager->fork(static fn(): int => 0, 0);
    throw new RuntimeException('Zero workers unexpectedly accepted.');
} catch (\FastForward\Fork\Exception\InvalidArgumentException) {
}

echo "fast-forward-fork: ok\n";
