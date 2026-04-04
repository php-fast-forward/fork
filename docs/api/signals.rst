Signals API
===========

The signals API consists of:

- ``FastForward\Fork\Signal\Signal``
- ``FastForward\Fork\Signal\SignalHandlerInterface``
- ``FastForward\Fork\Signal\DefaultSignalHandler``

Signal enum
-----------

``Signal`` is a typed enum that maps directly to supported POSIX signal values.

Examples:

- ``Signal::Terminate``
- ``Signal::Interrupt``
- ``Signal::Quit``
- ``Signal::Kill``
- ``Signal::User1``
- ``Signal::User2``

The enum also exposes ``exitStatus()`` for conventional shell-style
``128 + signal`` exit reporting.

Signal handler contract
-----------------------

``SignalHandlerInterface`` has two responsibilities:

- declare the signals it wants to subscribe to
- react when the manager receives one of those signals

Contract shape
^^^^^^^^^^^^^^

.. code-block:: php

   <?php

   declare(strict_types=1);

   use FastForward\Fork\Manager\ForkManagerInterface;
   use FastForward\Fork\Signal\Signal;
   use FastForward\Fork\Signal\SignalHandlerInterface;

   final class ExampleHandler implements SignalHandlerInterface
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

Default signal handler
----------------------

``DefaultSignalHandler`` is the built-in handler used when the manager is
constructed without a custom handler.

Configurable options
^^^^^^^^^^^^^^^^^^^^

.. list-table::
   :header-rows: 1

   * - Constructor argument
     - Purpose
   * - ``signals``
     - Which incoming signals should be subscribed to
   * - ``waitForWorkers``
     - Whether the master should wait for worker shutdown after propagation
   * - ``exitOnSignal``
     - Whether the current process should exit after signal handling completes
   * - ``escalationSignal``
     - Which signal should be sent if another signal arrives during shutdown

Default behavior
^^^^^^^^^^^^^^^^

- listens to interrupt, terminate, and quit
- normalizes interactive termination signals to ``Signal::Terminate`` for workers
- can wait for worker completion
- can exit after handling
- escalates to ``Signal::Kill`` on re-entrant shutdown

When to implement your own handler
----------------------------------

Write a custom handler when you need:

- application-specific reload behavior
- staged shutdown logic
- metrics or observability hooks
- custom escalation strategies
- selective signaling based on application rules
