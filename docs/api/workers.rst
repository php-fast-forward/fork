Workers and Worker Groups
=========================

This page covers the worker-facing part of the public API plus the internal
runtime helpers that make worker state and output capture possible.

Public types
------------

- ``FastForward\Fork\Worker\Worker``
- ``FastForward\Fork\Worker\WorkerInterface``
- ``FastForward\Fork\Worker\WorkerGroup``
- ``FastForward\Fork\Worker\WorkerGroupInterface``

Internal types
--------------

- ``FastForward\Fork\Worker\WorkerState``
- ``FastForward\Fork\Worker\WorkerOutputTransport``

Worker
------

``Worker`` represents one forked child process.

What you can inspect on a worker:

- PID
- running state
- raw process status
- normalized exit code
- termination signal
- captured stdout
- captured error output

Main methods
^^^^^^^^^^^^

.. list-table::
   :header-rows: 1

   * - Method
     - Description
   * - ``getPid()``
     - Returns the child PID
   * - ``isRunning()``
     - Returns whether the worker is still active
   * - ``getStatus()``
     - Returns the raw wait status when available
   * - ``getExitCode()``
     - Returns the normalized exit code for normal termination
   * - ``getTerminationSignal()``
     - Returns the terminating signal for signal-based termination
   * - ``getOutput()``
     - Returns captured stdout, possibly partial while still running
   * - ``getErrorOutput()``
     - Returns captured error output, possibly partial while still running
   * - ``wait()``
     - Waits for this worker only
   * - ``kill()``
     - Signals this worker only

Important constraint
^^^^^^^^^^^^^^^^^^^^

A worker cannot wait on itself. Attempting to do so raises a
``FastForward\Fork\Exception\LogicException``.

Worker groups
-------------

``WorkerGroup`` is an immutable, read-only collection of workers created by the
same manager.

Main methods
^^^^^^^^^^^^

.. list-table::
   :header-rows: 1

   * - Method
     - Description
   * - ``getManager()``
     - Returns the manager that created the group
   * - ``all()``
     - Returns all workers indexed by PID
   * - ``get(int $pid)``
     - Returns one worker by PID or ``null``
   * - ``getRunning()``
     - Returns only currently running workers
   * - ``getStopped()``
     - Returns only stopped workers
   * - ``wait()``
     - Waits for every worker in the group
   * - ``kill()``
     - Signals every worker in the group
   * - ``count()``
     - Returns the number of workers in the group

Internal worker state
---------------------

``WorkerState`` tracks:

- the PID
- whether the worker is still running
- the raw wait status
- the normalized exit code
- the terminating signal
- accumulated stdout and stderr

This state is shared between the public worker object and the manager's
monitoring loop.

Internal output transport
-------------------------

``WorkerOutputTransport`` manages socket pairs used to move worker output from
the child process to the parent.

Its responsibilities include:

- allocating socket pairs for stdout and error output
- splitting parent-side and child-side ownership
- supporting non-blocking reads in the master process
- accumulating output buffers
- optionally logging chunk-level output events

Stability note
--------------

``WorkerState`` and ``WorkerOutputTransport`` are implementation details. They
are useful to understand the architecture, but integrations should depend on the
manager, worker, and worker-group contracts instead.
