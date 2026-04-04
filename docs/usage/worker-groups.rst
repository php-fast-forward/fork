Worker Groups
=============

``WorkerGroup`` is the main batching abstraction in the public API. Every call
to ``ForkManager::fork()`` returns a fresh immutable group representing the
workers created by that call.

Core idea
---------

A group is:

- read-only from the perspective of userland code
- tied to the manager that created it
- iterable
- countable
- able to wait for or signal all workers it contains

Inspect all workers
-------------------

.. code-block:: php

   <?php

   foreach ($group->all() as $worker) {
       echo $worker->getPid() . PHP_EOL;
   }

Fetch a worker by PID
---------------------

.. code-block:: php

   <?php

   $worker = $group->get($pid);

   if ($worker !== null) {
       echo $worker->getOutput();
   }

Inspect running and stopped subsets
-----------------------------------

.. code-block:: php

   <?php

   $running = $group->getRunning();
   $stopped = $group->getStopped();

   printf("running=%d stopped=%d\n", count($running), count($stopped));

Wait for the whole group
------------------------

.. code-block:: php

   <?php

   $group->wait();

Signal the whole group
----------------------

.. code-block:: php

   <?php

   use FastForward\Fork\Signal\Signal;

   $group->kill(Signal::Terminate);
   $group->wait();

Gotchas
-------

- Groups do not create workers; they only expose workers created by the manager.
- A group is associated with one manager instance.
- Passing a group to another manager raises an ``InvalidArgumentException``.
- Group membership does not change after construction.

When to use the manager instead
-------------------------------

Use ``ForkManager::wait()`` or ``ForkManager::kill()`` directly when you need:

- to coordinate workers from multiple groups in a single call
- to target a mix of individual workers and whole groups
- to reconcile every worker managed by the same manager without tracking groups manually
