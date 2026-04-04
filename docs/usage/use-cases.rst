Use Cases
=========

This page collects realistic usage patterns that map well to the current public
API.

Parallel fan-out of independent tasks
-------------------------------------

Use one callback and multiple workers when each unit of work can be handled
independently.

.. code-block:: php

   <?php

   declare(strict_types=1);

   use FastForward\Fork\Manager\ForkManager;
   use FastForward\Fork\Worker\WorkerInterface;

   $manager = new ForkManager();

   $group = $manager->fork(
       static function (WorkerInterface $worker): int {
           echo sprintf("worker %d processing batch\n", $worker->getPid());
           usleep(200_000);

           return 0;
       },
       4,
   );

   $group->wait();

Long-running workers with graceful shutdown
-------------------------------------------

Combine a custom or default signal handler with long-running workers that emit
heartbeats or consume a queue.

.. code-block:: php

   <?php

   declare(strict_types=1);

   use FastForward\Fork\Manager\ForkManager;
   use FastForward\Fork\Signal\DefaultSignalHandler;
   use FastForward\Fork\Worker\WorkerInterface;

   $manager = new ForkManager(
       signalHandler: new DefaultSignalHandler(exitOnSignal: false),
   );

   $group = $manager->fork(
       static function (WorkerInterface $worker): int {
           while (true) {
               echo sprintf("worker %d heartbeat\n", $worker->getPid());
               usleep(100_000);
           }
       },
       2,
   );

Selective shutdown
------------------

If you create more than one group, the manager can target just a subset.

.. code-block:: php

   <?php

   declare(strict_types=1);

   use FastForward\Fork\Manager\ForkManager;
   use FastForward\Fork\Signal\Signal;
   use FastForward\Fork\Worker\WorkerInterface;

   $manager = new ForkManager();

   $apiGroup = $manager->fork(
       static fn (WorkerInterface $worker): int => 0,
       2,
   );

   $queueGroup = $manager->fork(
       static fn (WorkerInterface $worker): int => 0,
       2,
   );

   $queueWorkers = array_values($queueGroup->all());
   $selectedWorker = $queueWorkers[0];

   $manager->kill(Signal::Terminate, $apiGroup, $selectedWorker);
   $manager->wait($apiGroup, $selectedWorker);

Partial output inspection
-------------------------

You can use worker output as a progress channel while the work is still running.

.. code-block:: php

   <?php

   declare(strict_types=1);

   use FastForward\Fork\Manager\ForkManager;
   use FastForward\Fork\Worker\WorkerInterface;

   $manager = new ForkManager();

   $group = $manager->fork(
       static function (WorkerInterface $worker): int {
           echo sprintf("worker %d started\n", $worker->getPid());
           usleep(500_000);
           echo sprintf("worker %d finished\n", $worker->getPid());

           return 0;
       },
       2,
   );

   foreach ($group->all() as $worker) {
       echo $worker->getOutput();
   }

   $group->wait();

Worker-created subprocess trees
-------------------------------

If a worker needs its own subprocesses, instantiate a new manager inside that
worker. Do not reuse the parent manager instance.

This keeps the process tree explicit and avoids logic errors caused by mixing
multiple orchestration roots into one manager instance.
