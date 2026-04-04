Quickstart
==========

Minimal working example
-----------------------

The example below creates three workers, waits for the group, and then inspects
the result of each worker.

.. code-block:: php

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

What happens here
-----------------

1. ``ForkManager`` is created in the master process.
2. ``fork()`` spawns three workers that all run the same callback.
3. The callback receives the current ``WorkerInterface`` instance.
4. ``WorkerGroup::wait()`` blocks until all workers in that group finish.
5. Each worker can then be inspected for exit code, signal, stdout, and stderr.

Expected behavior
-----------------

- Each worker gets its own PID.
- Each worker runs independently.
- ``getExitCode()`` returns ``0`` when the callback returns ``0``.
- ``getTerminationSignal()`` stays ``null`` when the worker exits normally.
- ``getOutput()`` contains the buffered worker output captured by the parent.

Next steps
----------

After the quickstart, continue with:

- :doc:`../usage/getting-services`
- :doc:`../usage/worker-groups`
- :doc:`../usage/output-and-errors`
- :doc:`../usage/signals`
