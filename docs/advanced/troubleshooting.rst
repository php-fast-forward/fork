Troubleshooting
===============

Unsupported runtime
-------------------

Symptom:

- manager construction throws a runtime exception immediately

Typical cause:

- missing ``pcntl`` or ``posix`` support
- unsupported operating system
- CLI runtime not exposing the required functions

Recommended action:

- review :doc:`../getting-started/installation`
- verify the functions listed under runtime requirements
- construct the manager only in environments that are expected to support process control

Worker output is missing
------------------------

Symptom:

- ``getOutput()`` or ``getErrorOutput()`` is empty or incomplete

Typical cause:

- output was written directly to ``STDOUT`` or ``STDERR`` through native descriptor writes
- output was inspected before the parent had a chance to drain it

Recommended action:

- prefer buffered userland output like ``echo`` and ``printf``
- use manager- or group-level ``wait()`` for synchronization
- review :doc:`../usage/output-and-errors`

Worker tries to wait on itself
------------------------------

Symptom:

- a ``LogicException`` is raised from ``Worker::wait()``

Typical cause:

- the current worker process is trying to block on its own termination

Recommended action:

- call ``wait()`` from the master process
- if nested orchestration is needed, create a new manager inside the worker

Foreign worker or group errors
------------------------------

Symptom:

- an ``InvalidArgumentException`` is raised when signaling or waiting

Typical cause:

- mixing workers or groups from different manager instances

Recommended action:

- keep each worker tree associated with its originating manager
- do not pass workers from one manager to another manager's methods

Signal propagation does not behave as expected
----------------------------------------------

Symptom:

- workers do not shut down when the master receives a signal

Typical cause:

- no custom handler was injected when expected
- a handler was configured with options that do not match the desired behavior
- the application is running outside an environment that delivers POSIX signals reliably

Recommended action:

- review :doc:`../usage/signals`
- confirm the configured ``DefaultSignalHandler`` options
- if necessary, implement a custom ``SignalHandlerInterface``

Detached workers
----------------

Symptom:

- worker state indicates a detached process instead of a normally waited worker

Typical cause:

- the process disappeared before it could be fully reconciled by the wait loop

Recommended action:

- keep wait calls centralized in the master process
- avoid introducing competing low-level wait logic outside the manager
- use logging to inspect lifecycle order
