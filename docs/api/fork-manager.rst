Fork Manager
============

The manager is the orchestration root for a worker tree. Every worker created by
the library belongs to exactly one ``ForkManager`` instance.

Main types
----------

- ``FastForward\Fork\Manager\ForkManager``
- ``FastForward\Fork\Manager\ForkManagerInterface``

Responsibilities
----------------

``ForkManager`` is responsible for:

- validating runtime support
- spawning workers
- keeping a registry of managed workers
- waiting for selected workers or all managed workers
- signaling selected workers or all managed workers
- registering the configured signal handler
- differentiating master and worker execution contexts

Public methods
--------------

.. list-table::
   :header-rows: 1

   * - Method
     - Description
   * - ``isSupported()``
     - Reports whether the current runtime exposes the required process-control functions
   * - ``fork(callable $callback, int $workerCount = 1)``
     - Creates a new immutable worker group for one callback and ``N`` workers
   * - ``wait(WorkerInterface|WorkerGroupInterface ...$workers)``
     - Waits for selected targets or for all manager-owned workers when called without arguments
   * - ``kill(Signal $signal = Signal::Terminate, WorkerInterface|WorkerGroupInterface ...$workers)``
     - Sends a signal to selected targets or to all manager-owned workers when called without arguments
   * - ``getMasterPid()``
     - Returns the PID of the master process associated with the manager
   * - ``isMaster()``
     - Indicates whether the current execution context is the manager's master process
   * - ``isWorker()``
     - Indicates whether the current execution context is one of the forked worker processes

Construction
------------

The manager constructor accepts:

- a ``SignalHandlerInterface`` implementation
- an optional PSR-3 logger

.. code-block:: php

   <?php

   declare(strict_types=1);

   use FastForward\Fork\Manager\ForkManager;
   use FastForward\Fork\Signal\DefaultSignalHandler;

   $manager = new ForkManager(
       signalHandler: new DefaultSignalHandler(exitOnSignal: false),
   );

Behavioral notes
----------------

- The constructor throws immediately when the runtime is unsupported.
- The same manager instance cannot be reused from inside one of its own workers.
- ``wait()`` drains output while monitoring worker state, so it is the preferred
  synchronization point for long-running workers that emit output.
- The manager stores workers internally and returns immutable groups only for
  user interaction.

Extension points
----------------

The main extension point at manager level is signal handling:

- inject a custom ``SignalHandlerInterface``
- inject a PSR-3 logger

What the manager does not provide
---------------------------------

The library deliberately avoids:

- alias systems
- static bootstrap helpers
- service provider abstractions
- framework-specific factories

This keeps the orchestration model explicit and portable.
