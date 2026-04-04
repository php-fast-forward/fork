Exceptions
==========

The library exposes a small, explicit exception family.

Hierarchy overview
------------------

.. list-table::
   :header-rows: 1

   * - Type
     - Role
   * - ``ForkExceptionInterface``
     - Marker interface for all library-specific exceptions
   * - ``InvalidArgumentException``
     - Input validation and ownership errors
   * - ``LogicException``
     - Invalid lifecycle or control-flow usage
   * - ``RuntimeException``
     - Operational failures during process management

Invalid argument exceptions
---------------------------

Typical causes:

- worker count less than ``1``
- passing a worker from another manager
- passing a group from another manager
- using an unsupported worker implementation in a manager-targeted call

Logic exceptions
----------------

Typical causes:

- trying to reuse the same manager instance from inside one of its workers
- trying to make a worker wait on itself

Runtime exceptions
------------------

Typical causes:

- unsupported runtime
- failed ``pcntl_fork()``
- failed wait operations
- PID detection failure
- output transport allocation failure

Catching library-specific failures
----------------------------------

If you want to catch only library-owned failures, catch the shared interface.

.. code-block:: php

   <?php

   declare(strict_types=1);

   use FastForward\Fork\Exception\ForkExceptionInterface;
   use FastForward\Fork\Manager\ForkManager;

   try {
       $manager = new ForkManager();
   } catch (ForkExceptionInterface $exception) {
       echo $exception->getMessage();
   }
