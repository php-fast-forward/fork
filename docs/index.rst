Fast Forward Fork
=================

``fast-forward/fork`` is a PHP 8.3+ library for orchestrating forked worker
processes with a compact, strongly-typed API. It wraps low-level POSIX process
control primitives in a higher-level object model composed of a manager, worker
objects, immutable worker groups, typed signals, and named exceptions.

This package is designed for CLI-oriented workloads where you need explicit
control over process fan-out, lifecycle supervision, output capture, and
graceful shutdown. Typical scenarios include queue consumers, parallel task
dispatchers, long-running daemons, and controlled background process trees.

Useful links
------------

- `Repository <https://github.com/php-fast-forward/fork>`_
- `Packagist <https://packagist.org/packages/fast-forward/fork>`_
- `Issue Tracker <https://github.com/php-fast-forward/fork/issues>`_
- `Tests Workflow <https://github.com/php-fast-forward/fork/actions/workflows/tests.yml>`_
- `Reports Workflow <https://github.com/php-fast-forward/fork/actions/workflows/reports.yml>`_
- `README <https://github.com/php-fast-forward/fork/blob/main/README.md>`_

Highlights
----------

- A single ``ForkManager`` orchestrates worker creation, waiting, and signaling.
- Each ``Worker`` exposes PID, status, exit code, termination signal, stdout,
  and error output.
- ``WorkerGroup`` offers immutable grouping for batch coordination.
- ``Signal`` is a typed enum instead of a raw integer protocol.
- ``DefaultSignalHandler`` can propagate master-process signals to the worker tree.
- PSR-3 loggers can observe lifecycle and streamed output events.
- Worker output can be inspected before all workers finish.

.. toctree::
   :maxdepth: 2
   :caption: Contents:

   getting-started/index
   usage/index
   advanced/index
   api/index
   links/index
   faq
   compatibility
