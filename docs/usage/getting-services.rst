Getting Services
================

This library is intentionally lightweight. There is no framework-specific
service provider, alias registry, or singleton helper. The primary entry point
is direct construction of ``ForkManager``.

Direct instantiation
--------------------

.. code-block:: php

   <?php

   declare(strict_types=1);

   use FastForward\Fork\Manager\ForkManager;

   $manager = new ForkManager();

Instantiating with a custom signal handler
------------------------------------------

.. code-block:: php

   <?php

   declare(strict_types=1);

   use FastForward\Fork\Manager\ForkManager;
   use FastForward\Fork\Signal\DefaultSignalHandler;

   $manager = new ForkManager(
       signalHandler: new DefaultSignalHandler(exitOnSignal: false),
   );

Instantiating with a PSR-3 logger
---------------------------------

.. code-block:: php

   <?php

   declare(strict_types=1);

   use FastForward\Fork\Manager\ForkManager;
   use Monolog\Handler\StreamHandler;
   use Monolog\Logger;

   $logger = new Logger('fork');
   $logger->pushHandler(new StreamHandler('php://stdout'));

   $manager = new ForkManager(logger: $logger);

Container registration
----------------------

The package does not depend on PSR-11, but it integrates cleanly with any
container that can execute a factory.

.. code-block:: php

   <?php

   declare(strict_types=1);

   use FastForward\Fork\Manager\ForkManager;
   use FastForward\Fork\Signal\DefaultSignalHandler;
   use Psr\Log\LoggerInterface;

   $container->set(ForkManager::class, static function () use ($container): ForkManager {
       return new ForkManager(
           signalHandler: new DefaultSignalHandler(exitOnSignal: false),
           logger: $container->get(LoggerInterface::class),
       );
   });

Creating a manager inside a worker
----------------------------------

The same manager instance cannot be reused from inside one of its own workers.
If you need a nested process tree, create a new manager inside the worker
callback.

.. code-block:: php

   <?php

   declare(strict_types=1);

   use FastForward\Fork\Manager\ForkManager;
   use FastForward\Fork\Worker\WorkerInterface;

   $manager = new ForkManager();

   $outer = $manager->fork(
       static function (WorkerInterface $worker): int {
           $nestedManager = new ForkManager();
           $nestedGroup = $nestedManager->fork(
               static fn (WorkerInterface $nestedWorker): int => 0,
               2,
           );

           $nestedGroup->wait();

           return 0;
       },
       1,
   );

   $outer->wait();

What is not provided
--------------------

The library currently does not provide:

- static bootstrap helpers
- singleton accessors
- alias maps
- framework-specific service providers

That is intentional. The package keeps orchestration explicit and leaves service
registration to the hosting application.
