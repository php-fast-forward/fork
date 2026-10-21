---
name: fast-forward-fork
description: Run Fast Forward fork workers on PCNTL/POSIX CLI runtimes, wait for output and exit codes, and handle failed workers and invalid counts.
license: MIT; see LICENSE
compatibility: Supports fast-forward/fork ^1.1 and PHP ^8.3. Requires Composer and declared extensions; inspect the installed version. Earlier releases are outside this contract. Requires PCNTL/POSIX in a dedicated Unix CLI process.
metadata:
  author: Fast Forward
  api-baseline: fast-forward/fork ^1.1
---

# Run bounded CLI worker processes

Use this skill for an application consuming `fast-forward/fork:^1.1`.
Read its instructions, manifest/lockfile and installed source before editing.
Run `composer show fast-forward/fork`. For versions outside this contract,
inspect matching documentation; do not silently upgrade the application.

## First working integration

Requires PHP ^8.3, Composer's application autoloader and this package's runtime
dependencies. Run as a bounded separate CLI process with PCNTL/POSIX.
With authority to install the dependency:

```sh
composer require 'fast-forward/fork:^1.1'
```

Create `app.php` in the application root alongside composer.json/vendor:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

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

echo "integration ready\n";
```

Run `php app.php`. It throws on a wrong result; success prints
`integration ready`. It uses no production data and starts no server.

## Wiring and failure boundaries

Run in a dedicated CLI process on Unix with PCNTL, POSIX and stream socket/select support. The Composer metadata does not currently enforce all of these runtime functions; check before constructing ForkManager. This library is not a Windows fallback, thread pool or background job service.

ForkManager registers asynchronous process signal handlers at construction. Keep it inside a worker supervisor you own; do not instantiate it in a web request or a test process that must preserve unrelated signal policies. Run the example as a separate bounded process.

fork($callback, $workerCount) starts children immediately and returns a WorkerGroupInterface. The callback receives WorkerInterface. wait() drains child output and collects status. Objects and open connections are inherited at fork: reconnect database/network clients inside the child, and use explicit IPC/storage for results. Captured variables are not shared parent memory.

Read getOutput(), getErrorOutput(), getExitCode() and getTerminationSignal() after waiting. Integer callback results are clamped to 0..255; false maps to 1 and normal noninteger results to 0. An uncaught throwable becomes a failed child, not a parent exception. Invalid counts, foreign workers and attempts to fork from a worker raise library exceptions. kill(Signal::Terminate) is cooperative; design deadlines and follow-up waiting in your supervisor. No container provider or application config schema is supplied.

## Verify integration

Run the [complete example](examples/integration.php) against the application's
autoloader in a disposable process. It checks success and negative cases:

```sh
php /path/to/fast-forward-fork/examples/integration.php ./vendor/autoload.php
```

Expected final marker: `fast-forward-fork: ok`. Keep application-specific regression
tests in its existing PHPUnit suite, with each fixture class in its same-named
file. File copying, host discovery and completed agent tasks are different checks.
For optional copying/synchronization load
[installation and lifecycle](references/installation.md).
