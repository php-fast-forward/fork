FAQ
===

Why does the manager throw on unsupported runtimes instead of running synchronously?
-------------------------------------------------------------------------------------

Because the library is intentionally a real process orchestration package. Silent
fallback to synchronous execution would change semantics and make lifecycle
coordination misleading. See :doc:`getting-started/installation`.

Can I wait for every worker without storing each group?
-------------------------------------------------------

Yes. Calling ``$manager->wait()`` with no arguments waits for every worker
created by that manager. See :doc:`usage/worker-groups`.

Can I signal only one worker?
-----------------------------

Yes. ``Worker::kill()`` targets a single worker, and ``ForkManager::kill()``
can mix individual workers and worker groups in the same call. See
:doc:`usage/signals`.

Can I read output before workers finish?
----------------------------------------

Yes. ``getOutput()`` and ``getErrorOutput()`` may return partial output while
workers are still running. See :doc:`usage/output-and-errors`.

Why is direct ``fwrite(STDOUT, ...)`` not captured?
---------------------------------------------------

Because the library currently captures buffered userland output through its own
transport instead of redirecting native file descriptors. See
:doc:`usage/output-and-errors`.

Can I reuse the same manager inside a worker to create nested workers?
----------------------------------------------------------------------

No. The same manager instance cannot be reused from inside one of its workers.
Create a new manager inside that worker if you need a nested process tree. See
:doc:`usage/getting-services`.

Does the library provide container aliases or singletons?
---------------------------------------------------------

No. The package intentionally does not include aliases, singleton helpers, or
service-provider abstractions. See :doc:`advanced/extending`.

Can I integrate the manager with a PSR-11 container?
----------------------------------------------------

Yes. The package is not coupled to PSR-11, but it can be registered through a
factory in any application container. See :doc:`usage/getting-services` and
:doc:`advanced/integration`.

Can I inject my own logger?
---------------------------

Yes. Pass any ``Psr\Log\LoggerInterface`` implementation to the manager
constructor. See :doc:`advanced/integration`.

What exception should I catch if I only care about library-owned failures?
--------------------------------------------------------------------------

Catch ``FastForward\Fork\Exception\ForkExceptionInterface``. See
:doc:`api/exceptions`.

Does this package work for standard HTTP request handling?
----------------------------------------------------------

Not as a primary target. It is designed for CLI-oriented runtimes with POSIX
process control. See :doc:`compatibility`.

Where should I start if I want a hands-on walkthrough?
------------------------------------------------------

Start with the numbered examples in ``examples/`` and the quickstart page in
:doc:`getting-started/quickstart`.
