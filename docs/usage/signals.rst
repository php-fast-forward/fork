Signals
=======

Signals are modeled through the typed ``FastForward\Fork\Signal\Signal`` enum.
This avoids leaking raw signal integers through the public API and makes signal
delivery easier to read and review.

Using the ``Signal`` enum
-------------------------

.. code-block:: php

   <?php

   use FastForward\Fork\Signal\Signal;

   $group->kill(Signal::Terminate);

Available public signals
------------------------

The enum currently exposes common POSIX signals used for process coordination,
including:

- ``Signal::Interrupt``
- ``Signal::Quit``
- ``Signal::Terminate``
- ``Signal::Kill``
- ``Signal::User1``
- ``Signal::User2``
- ``Signal::Child``
- ``Signal::Continue``
- ``Signal::Stop``
- ``Signal::TerminalStop``

Signal workers directly
-----------------------

.. code-block:: php

   <?php

   use FastForward\Fork\Signal\Signal;

   $worker->kill(Signal::Terminate);
   $worker->wait();

Signal a whole group
--------------------

.. code-block:: php

   <?php

   use FastForward\Fork\Signal\Signal;

   $group->kill(Signal::Terminate);
   $group->wait();

Signal a subset through the manager
-----------------------------------

.. code-block:: php

   <?php

   use FastForward\Fork\Signal\Signal;

   $manager->kill(Signal::Terminate, $groupA, $workerB);
   $manager->wait($groupA, $workerB);

Default signal handling
-----------------------

``DefaultSignalHandler`` is the built-in strategy for master-process signal
propagation.

Its main behaviors are:

- subscribe to a configurable list of signals
- normalize interactive termination signals to ``Signal::Terminate`` for workers
- optionally wait for worker shutdown
- optionally exit the current process after handling
- escalate to ``Signal::Kill`` if a second signal arrives during shutdown

Example
-------

.. code-block:: php

   <?php

   declare(strict_types=1);

   use FastForward\Fork\Manager\ForkManager;
   use FastForward\Fork\Signal\DefaultSignalHandler;
   use FastForward\Fork\Signal\Signal;

   $manager = new ForkManager(
       signalHandler: new DefaultSignalHandler(exitOnSignal: false),
   );

   posix_kill($manager->getMasterPid(), Signal::Terminate->value);

Signal-aware exit status
------------------------

``Signal::exitStatus()`` converts a signal into the conventional shell exit
status of ``128 + signal_number``.

This is useful when a signal handler decides to terminate the current process
after propagating shutdown to workers.
