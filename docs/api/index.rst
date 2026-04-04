API Reference
=============

The public API is intentionally compact. Most applications interact with only
four conceptual areas:

- manager orchestration
- worker and worker-group inspection
- signal modeling and handling
- named exception types

Overview
--------

.. list-table::
   :header-rows: 1

   * - Area
     - Main types
     - Purpose
   * - Manager
     - ``ForkManager``, ``ForkManagerInterface``
     - Create workers, wait for them, signal them, and identify master vs worker context
   * - Workers
     - ``Worker``, ``WorkerInterface``, ``WorkerGroup``, ``WorkerGroupInterface``
     - Represent one worker or a read-only batch of workers
   * - Signals
     - ``Signal``, ``SignalHandlerInterface``, ``DefaultSignalHandler``
     - Model POSIX signals and define shutdown propagation behavior
   * - Exceptions
     - ``ForkExceptionInterface`` and concrete exception classes
     - Report invalid arguments, logic violations, and runtime failures

Internal helpers
----------------

Two internal types support the public API:

- ``WorkerState``
- ``WorkerOutputTransport``

They are documented for architectural understanding, but they are not intended
to be part of the stable user-facing integration surface.

.. toctree::
   :maxdepth: 2

   fork-manager
   workers
   signals
   exceptions
