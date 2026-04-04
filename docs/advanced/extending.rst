Extending and Customizing
=========================

The library keeps its public surface small, but there are still clear points
where applications can customize behavior.

Primary extension points
------------------------

The main supported customization points are:

- custom ``SignalHandlerInterface`` implementations
- PSR-3 logger injection
- application-specific worker callback design
- application-specific container registration and lifecycle policies

Custom signal handlers
----------------------

The most direct extension point is the signal handler.

.. code-block:: php

   <?php

   declare(strict_types=1);

   use FastForward\Fork\Manager\ForkManager;
   use FastForward\Fork\Manager\ForkManagerInterface;
   use FastForward\Fork\Signal\Signal;
   use FastForward\Fork\Signal\SignalHandlerInterface;

   final class ReloadSignalHandler implements SignalHandlerInterface
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

   $manager = new ForkManager(signalHandler: new ReloadSignalHandler());

Custom lifecycle policies
-------------------------

Because the manager API is explicit, applications can define their own
lifecycle policy on top:

- whether to call ``wait()`` per group or globally
- how to choose which groups receive termination signals
- how to classify worker output as business metrics or diagnostics
- how to react to specific non-zero exit codes

No aliases or singleton helpers
--------------------------------

The codebase currently does not expose:

- aliases
- singleton accessors
- static service locators
- override registries

That absence is intentional and should be considered part of the package design.
Applications are expected to compose these patterns externally if they want them.

Internal architecture note
--------------------------

``WorkerState`` and ``WorkerOutputTransport`` are internal helpers. They are
documented in :doc:`../api/workers` for architectural clarity, but they should
not be treated as extension points with the same stability expectations as the
public manager, worker, and signal contracts.
