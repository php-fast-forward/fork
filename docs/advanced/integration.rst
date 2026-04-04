Integration
===========

This package is runtime-focused rather than framework-focused. It integrates
well with containers, loggers, supervisors, and CLI applications because its
dependencies are minimal and its public API is explicit.

PSR-3 logging
-------------

The manager accepts an optional ``Psr\Log\LoggerInterface``. Use this when you
want to observe:

- worker creation
- worker termination
- chunk-level stdout events
- chunk-level error-output events

Example with Monolog
--------------------

.. code-block:: php

   <?php

   declare(strict_types=1);

   use FastForward\Fork\Manager\ForkManager;
   use Monolog\Handler\StreamHandler;
   use Monolog\Logger;

   $logger = new Logger('fork');
   $logger->pushHandler(new StreamHandler('php://stdout'));

   $manager = new ForkManager(logger: $logger);

PSR-11 containers
-----------------

The package is not coupled to PSR-11, but it is easy to register in a container
as a factory-backed service.

Integration guidelines:

- register the manager as a factory, not as an eagerly-instantiated singleton
- inject the logger and signal handler through the factory
- let construction fail loudly in unsupported environments

CLI process supervisors
-----------------------

This library is a good fit under external supervisors such as:

- systemd units
- Supervisor-managed CLI processes
- container entrypoints that run long-lived PHP workers

Recommended pattern:

- let the supervisor own the master process
- let ``ForkManager`` own the worker tree beneath that process
- use a signal handler for graceful shutdown

Framework integration notes
---------------------------

The package does not ship with framework-specific adapters. Inference from the
public API:

- it can be integrated into Symfony, Laravel, Mezzio, or custom frameworks as a
  plain service
- it is better suited to command handlers, queues, and daemons than HTTP request lifecycles
- it should be constructed only in execution paths that genuinely need process control

Cross-package considerations
----------------------------

Within the Fast Forward ecosystem, this package complements tooling-oriented and
CLI-oriented components. It does not require special coupling to other packages
to be useful.
