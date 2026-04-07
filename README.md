# Fast Forward Fork

[![PHP Version](https://img.shields.io/badge/php-^8.3-777BB4?logo=php&logoColor=white)](https://www.php.net/releases/)
[![Tests](https://img.shields.io/github/actions/workflow/status/php-fast-forward/fork/tests.yml?logo=githubactions&logoColor=white&label=tests&color=22C55E)](https://github.com/php-fast-forward/fork/actions/workflows/tests.yml)
[![Coverage](https://img.shields.io/badge/coverage-phpunit-4ADE80?logo=php&logoColor=white)](https://php-fast-forward.github.io/fork/coverage/index.html)
[![Docs](https://img.shields.io/github/deployments/php-fast-forward/fork/github-pages?logo=readthedocs&logoColor=white&label=docs&labelColor=1E293B&color=38BDF8&style=flat)](https://php-fast-forward.github.io/fork/index.html)
[![License](https://img.shields.io/github/license/php-fast-forward/dev-tools?color=64748B)](LICENSE)
[![GitHub Sponsors](https://img.shields.io/github/sponsors/php-fast-forward?logo=githubsponsors&logoColor=white&color=EC4899)](https://github.com/sponsors/php-fast-forward)

A PHP 8.3+ library for orchestrating forked workers with typed signals, immutable worker groups,
captured worker output, and PSR-3 logging.

[![PHP Version](https://img.shields.io/badge/php-%5E8.3-777BB4.svg)](https://www.php.net/releases/)
[![Packagist Version](https://img.shields.io/packagist/v/fast-forward/fork.svg)](https://packagist.org/packages/fast-forward/fork)
[![Packagist Downloads](https://img.shields.io/packagist/dt/fast-forward/fork.svg)](https://packagist.org/packages/fast-forward/fork)
[![License](https://img.shields.io/github/license/php-fast-forward/fork.svg)](LICENSE)
[![Tests](https://github.com/php-fast-forward/fork/actions/workflows/tests.yml/badge.svg)](https://github.com/php-fast-forward/fork/actions/workflows/tests.yml)
[![Reports](https://github.com/php-fast-forward/fork/actions/workflows/reports.yml/badge.svg)](https://github.com/php-fast-forward/fork/actions/workflows/reports.yml)

`fast-forward/fork` wraps `pcntl_fork()` and related POSIX primitives in a small, strongly-typed API
that is easier to reason about in real applications. It gives you a single manager for orchestration,
explicit worker objects for lifecycle inspection, immutable worker groups for batch coordination, and a
default signal handler that can shut everything down cleanly.

## ✨ Features

- 🚀 PHP 8.3+ API with enums, readonly dependencies, and clear object boundaries.
- 🧵 `ForkManager` orchestration for spawning, waiting, and signaling workers.
- 👷 `Worker` objects with lifecycle state, exit code, termination signal, and captured output.
- 📦 Immutable `WorkerGroup` collections for batch `wait()` and `kill()` operations.
- 🛑 Typed `Signal` enum for readable signal delivery and signal-aware exit status handling.
- 🔔 Pluggable `SignalHandlerInterface` with a ready-to-use `DefaultSignalHandler`.
- 📝 PSR-3 logger integration for worker lifecycle and streamed output events.
- 📡 Partial output availability while workers are still running.
- 🧪 Ordered examples that go from basic usage to advanced coordination scenarios.
- ⚠️ Named library exceptions for invalid usage, logic violations, and runtime failures.

## 📦 Installation

```bash
composer require fast-forward/fork
```

### Runtime requirements

- PHP `^8.3`
- `psr/log` `^3.0`
- A runtime that exposes the process-control functions checked by `ForkManager::isSupported()`
- In practice, this means a Unix-like CLI runtime with `pcntl` and `posix` support enabled

The manager validates runtime support during construction. If the environment does not support
forking safely, it throws `FastForward\Fork\Exception\RuntimeException`.

## 🛠️ Usage

### Basic usage

```php
<?php

declare(strict_types=1);

use FastForward\Fork\Manager\ForkManager;
use FastForward\Fork\Worker\WorkerInterface;

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

$group->wait();

foreach ($group as $worker) {
    printf(
        "pid=%d exit=%s signal=%s\n",
        $worker->getPid(),
        var_export($worker->getExitCode(), true),
        $worker->getTerminationSignal()?->name ?? 'none',
    );
}
```

### Wait for everything managed by the same manager

```php
<?php

declare(strict_types=1);

use FastForward\Fork\Manager\ForkManager;
use FastForward\Fork\Worker\WorkerInterface;

$manager = new ForkManager();

$apiWorkers = $manager->fork(
    static fn (WorkerInterface $worker): int => 0,
    2,
);

$queueWorkers = $manager->fork(
    static fn (WorkerInterface $worker): int => 0,
    2,
);

// Waits for every worker created by this manager.
$manager->wait();
```

### Read worker output before completion

```php
<?php

declare(strict_types=1);

use FastForward\Fork\Manager\ForkManager;
use FastForward\Fork\Worker\WorkerInterface;

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

foreach ($group->all() as $worker) {
    echo $worker->getOutput();
}

$group->wait();
```

### Stop workers explicitly

```php
<?php

declare(strict_types=1);

use FastForward\Fork\Manager\ForkManager;
use FastForward\Fork\Signal\Signal;
use FastForward\Fork\Worker\WorkerInterface;

$manager = new ForkManager();

$group = $manager->fork(
    static function (WorkerInterface $worker): never {
        while (true) {
            echo sprintf("worker %d heartbeat\n", $worker->getPid());
            usleep(100_000);
        }
    },
    2,
);

$group->kill(Signal::Terminate);
$group->wait();
```

### Use the default signal handler

```php
<?php

declare(strict_types=1);

use FastForward\Fork\Manager\ForkManager;
use FastForward\Fork\Signal\DefaultSignalHandler;
use FastForward\Fork\Signal\Signal;

$manager = new ForkManager(
    signalHandler: new DefaultSignalHandler(exitOnSignal: false),
);

// Later, in the master process:
posix_kill($manager->getMasterPid(), Signal::Terminate->value);
```

## 🧰 API Summary

### Core classes

| Class | Responsibility | Highlights |
|--------|----------------|------------|
| `FastForward\Fork\Manager\ForkManager` | Master orchestration | `fork()`, `wait()`, `kill()`, `getMasterPid()` |
| `FastForward\Fork\Worker\Worker` | One forked worker | PID, exit code, termination signal, stdout, stderr |
| `FastForward\Fork\Worker\WorkerGroup` | Immutable worker collection | `all()`, `get(pid)`, `getRunning()`, `getStopped()`, `wait()`, `kill()` |
| `FastForward\Fork\Signal\Signal` | Typed POSIX signal enum | `Signal::Terminate`, `Signal::Kill`, `Signal::Interrupt`, `exitStatus()` |
| `FastForward\Fork\Signal\DefaultSignalHandler` | Ready-made signal propagation strategy | Graceful propagation, optional wait, escalation support |

### Main methods

| Target | Method | Description |
|--------|--------|-------------|
| `ForkManager` | `fork(callable $callback, int $workerCount = 1)` | Spawn `N` workers for the same callback and return them as a group |
| `ForkManager` | `wait(WorkerInterface\|WorkerGroupInterface ...$workers)` | Wait for targeted workers or every worker managed by the manager |
| `ForkManager` | `kill(Signal $signal = Signal::Terminate, WorkerInterface\|WorkerGroupInterface ...$workers)` | Send a signal to targeted workers or all managed workers |
| `Worker` | `wait()` | Wait for a single worker |
| `Worker` | `kill()` | Signal a single worker |
| `Worker` | `getOutput()` / `getErrorOutput()` | Read captured output, including partial output while still running |
| `WorkerGroup` | `wait()` | Wait for every worker in the group |
| `WorkerGroup` | `kill()` | Signal every worker in the group |
| `WorkerGroup` | `getRunning()` / `getStopped()` | Inspect current group state |

### Exceptions

| Exception | Use case |
|-----------|----------|
| `FastForward\Fork\Exception\InvalidArgumentException` | Invalid worker count, foreign worker, foreign worker group |
| `FastForward\Fork\Exception\LogicException` | Invalid control-flow usage, such as forking from a worker using the same manager |
| `FastForward\Fork\Exception\RuntimeException` | Unsupported runtime, fork failure, wait failure, transport allocation failure |
| `FastForward\Fork\Exception\ForkExceptionInterface` | Catch-all contract for library-specific exceptions |

## 🔌 Integration

This library is intentionally small and integrates cleanly with CLI-oriented PHP applications:

- `PSR-3`: inject any `Psr\Log\LoggerInterface` into `ForkManager` to trace worker lifecycle and streamed output
- POSIX signals: use the typed `Signal` enum instead of raw integers throughout your own code
- Custom shutdown logic: implement `FastForward\Fork\Signal\SignalHandlerInterface` and inject it into the manager
- Fast Forward tooling: the repository already includes ordered examples and GitHub workflows that fit the rest of the ecosystem

This package is best suited for:

- queue consumers
- parallel CLI jobs
- daemons and long-running processes
- controlled fan-out tasks where you want explicit lifecycle ownership

## 📁 Directory Structure

```text
src/
├── Exception/
│   ├── ForkExceptionInterface.php
│   ├── InvalidArgumentException.php
│   ├── LogicException.php
│   └── RuntimeException.php
├── Manager/
│   ├── ForkManager.php
│   └── ForkManagerInterface.php
├── Signal/
│   ├── DefaultSignalHandler.php
│   ├── Signal.php
│   └── SignalHandlerInterface.php
└── Worker/
    ├── Worker.php
    ├── WorkerGroup.php
    ├── WorkerGroupInterface.php
    ├── WorkerInterface.php
    ├── WorkerOutputTransport.php
    └── WorkerState.php

examples/
├── 01-basic-fork.php
├── 02-inspect-worker-group.php
├── 03-manager-wait-all.php
├── 04-stream-worker-output.php
├── 05-capture-worker-errors.php
├── 06-group-kill.php
├── 07-targeted-manager-control.php
├── 08-logger-integration.php
├── 09-default-signal-handler.php
├── 10-verify-library-behavior.php
├── bootstrap.php
└── Support/
```

## ⚙️ Advanced and Customization

### Custom signal handling

You can replace the default shutdown strategy entirely by implementing
`FastForward\Fork\Signal\SignalHandlerInterface` and injecting it into the manager:

```php
<?php

declare(strict_types=1);

use FastForward\Fork\Manager\ForkManager;
use FastForward\Fork\Manager\ForkManagerInterface;
use FastForward\Fork\Signal\Signal;
use FastForward\Fork\Signal\SignalHandlerInterface;

final class GracefulReloadHandler implements SignalHandlerInterface
{
    public function signals(): array
    {
        return [Signal::User1];
    }

    public function __invoke(ForkManagerInterface $manager, Signal $signal): void
    {
        $manager->kill(Signal::Terminate);
        $manager->wait();
    }
}

$manager = new ForkManager(signalHandler: new GracefulReloadHandler());
```

### Output capture model

The library transfers worker output through socket pairs so the master process can inspect partial or
final output while workers run.

What is captured:

- `echo`, `print`, `printf`, and other output-buffered userland output
- warnings routed through the worker error handler
- exceptions caught by the worker wrapper

What is not intercepted automatically:

- direct writes to native file descriptors such as `fwrite(STDOUT, ...)`
- direct writes to `STDERR` that bypass the worker wrapper

If you need descriptor-level capture, you will need explicit descriptor redirection in your own worker code.

### Nested process trees

The same `ForkManager` instance cannot be reused from inside a worker process. If a worker needs to create
its own child workers, instantiate a new manager inside that worker process.

## 🛠️ Versioning and Upgrade Notes

- The Composer branch alias currently exposes the development line as `1.x-dev`
- The public API is already split into focused namespaces: `Manager`, `Worker`, `Signal`, and `Exception`
- Before adopting new development versions, review release notes and examples because process orchestration
  libraries are sensitive to runtime and API details

## ❓ FAQ

**Does this package emulate workers when `pcntl` or `posix` are missing?**  
No. The manager fails explicitly with `RuntimeException` when the runtime is not supported.

**Can I read worker output before `wait()` finishes?**  
Yes. `Worker::getOutput()` and `Worker::getErrorOutput()` can expose partial output while the worker is still running.

**Can I wait for everything without tracking each group manually?**  
Yes. Calling `$manager->wait()` with no arguments waits for every worker created by that manager.

**Can I kill only some workers?**  
Yes. You can target individual `Worker` instances, entire `WorkerGroup` instances, or mix both in a single manager call.

**Can a worker reuse the parent manager to create nested workers?**  
No. A worker must create a new manager if it needs its own process tree.

**Does this library work in web requests?**  
It is designed for CLI-like runtimes with POSIX process control available. Inference from the required functions:
it is generally a poor fit for standard web SAPIs and unsupported on environments without `pcntl`/`posix`.

## 📊 Comparison

| Capability | `fast-forward/fork` | Manual `pcntl_*` orchestration |
|------------|---------------------|--------------------------------|
| Typed signals via enum | ✅ | ❌ |
| Immutable worker groups | ✅ | ❌ |
| Worker objects with state inspection | ✅ | ❌ |
| Partial output capture | ✅ | ❌ |
| PSR-3 logger integration | ✅ | ❌ |
| Default signal propagation strategy | ✅ | ❌ |
| Named library exceptions | ✅ | ❌ |
| Ordered learning examples | ✅ | ❌ |

## 🧪 Examples

The repository includes a numbered progression of examples:

- [examples/01-basic-fork.php](examples/01-basic-fork.php)
- [examples/02-inspect-worker-group.php](examples/02-inspect-worker-group.php)
- [examples/03-manager-wait-all.php](examples/03-manager-wait-all.php)
- [examples/04-stream-worker-output.php](examples/04-stream-worker-output.php)
- [examples/05-capture-worker-errors.php](examples/05-capture-worker-errors.php)
- [examples/06-group-kill.php](examples/06-group-kill.php)
- [examples/07-targeted-manager-control.php](examples/07-targeted-manager-control.php)
- [examples/08-logger-integration.php](examples/08-logger-integration.php)
- [examples/09-default-signal-handler.php](examples/09-default-signal-handler.php)
- [examples/10-verify-library-behavior.php](examples/10-verify-library-behavior.php)
- [examples/README.md](examples/README.md)

## 🛡 License

MIT © 2026 [Felipe Sayão Lobato Abreu](https://github.com/mentordosnerds)

See [LICENSE](LICENSE) for details.

## 🤝 Contributing

Issues, ideas, and pull requests are welcome.

Recommended local checks:

```bash
find src examples -name '*.php' -print0 | xargs -0 -n1 php -l
vendor/bin/phpstan analyse src examples --no-progress --debug
php examples/10-verify-library-behavior.php
```

If you use the Fast Forward dev tooling in this repository, you can also run:

```bash
composer dev-tools
composer dev-tools:fix
```

## 🔗 Links

- [Repository](https://github.com/php-fast-forward/fork)
- [Packagist](https://packagist.org/packages/fast-forward/fork)
- [Issues](https://github.com/php-fast-forward/fork/issues)
- [Examples](examples/README.md)
- [PHP Process Control (`pcntl`)](https://www.php.net/manual/en/book.pcntl.php)
- [PHP POSIX Functions](https://www.php.net/manual/en/book.posix.php)
- [PSR-3 Logger Interface](https://www.php-fig.org/psr/psr-3/)
- [RFC 2119](https://datatracker.ietf.org/doc/html/rfc2119)

Keywords for discoverability: PHP worker pool, `pcntl_fork` wrapper, POSIX signal handling, process manager,
parallel CLI jobs, worker orchestration, PSR-3 logging, Fast Forward PHP.
