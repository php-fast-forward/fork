Installation
============

Requirements
------------

The package targets CLI-style process orchestration. It is not a generic
fallback concurrency abstraction.

Minimum requirements:

- PHP ``^8.3``
- Composer
- A Unix-like runtime with process control support
- The PHP functions checked by ``ForkManager::isSupported()``

In practice, the runtime must expose:

- ``pcntl_async_signals``
- ``pcntl_fork``
- ``pcntl_signal``
- ``pcntl_waitpid``
- ``posix_getpid``
- ``posix_kill``
- ``stream_socket_pair``
- ``stream_select``

Installation command
--------------------

.. code-block:: bash

   composer require fast-forward/fork

Direct runtime dependency
-------------------------

The package has a deliberately small runtime dependency surface:

.. list-table::
   :header-rows: 1

   * - Dependency
     - Type
     - Purpose
   * - ``php``
     - Platform
     - Provides the runtime and process-control extensions required by the library
   * - ``psr/log``
     - Composer package
     - Allows injecting a PSR-3 logger into the manager

What happens on unsupported runtimes
------------------------------------

The manager validates support during construction. If the runtime is missing
required process-control capabilities, construction fails explicitly with
``FastForward\Fork\Exception\RuntimeException``.

.. code-block:: php

   <?php

   declare(strict_types=1);

   use FastForward\Fork\Exception\RuntimeException;
   use FastForward\Fork\Manager\ForkManager;

   try {
       $manager = new ForkManager();
   } catch (RuntimeException $exception) {
       echo $exception->getMessage();
   }

Environment notes
-----------------

- This library is intended for CLI-oriented environments.
- It is generally a poor fit for standard web request lifecycles.
- The same manager instance cannot be reused from inside a worker process.
- If a worker needs its own subprocess tree, create a new manager inside the
  worker callback.

Integration tip
---------------

If your application already uses dependency injection, register the manager as a
service factory rather than constructing it globally at bootstrap time. That
keeps runtime errors localized to the part of the application that actually
needs process control.
