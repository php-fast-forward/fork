Compatibility
=============

Version and runtime matrix
--------------------------

.. list-table::
   :header-rows: 1

   * - Area
     - Current expectation
     - Notes
   * - PHP
     - ``^8.3``
     - Declared in ``composer.json``
   * - Operating system
     - Unix-like environments
     - Requires POSIX process-control support
   * - Typical SAPI
     - CLI
     - Best fit for commands, daemons, and workers
   * - Windows
     - Not supported in practice
     - ``pcntl`` and ``posix`` requirements make this a poor fit
   * - Web request lifecycle
     - Not recommended
     - The library is built for explicit process orchestration, not ordinary HTTP requests

Runtime compatibility notes
---------------------------

- The manager constructor enforces runtime support at creation time.
- The package expects real forking support; it does not emulate workers on unsupported runtimes.
- The output model assumes stream and socket support consistent with the checked functions.

API compatibility notes
-----------------------

- The public API is organized around ``Manager``, ``Worker``, ``Signal``, and ``Exception`` namespaces.
- Worker groups are immutable views returned from fork operations.
- The same manager instance cannot be reused from inside its own worker process.

Development line
----------------

The Composer branch alias currently maps the development branch to ``1.x-dev``.

Upgrade guidance
----------------

When upgrading:

- re-run the numbered examples under ``examples/``
- re-check runtime assumptions in the target environment
- review the README and this documentation for changes in orchestration semantics

Practical compatibility checklist
---------------------------------

Before adopting the package in production, confirm:

- your deployment environment supports ``pcntl`` and ``posix``
- your application runs the package from a CLI-like context
- your logging strategy is ready for optional PSR-3 integration
- your worker callbacks use buffered userland output if output capture matters
