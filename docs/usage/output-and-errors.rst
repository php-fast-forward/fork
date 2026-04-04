Output and Errors
=================

The library captures worker output through socket pairs managed between the
parent and child processes. This lets the master process inspect output while
the worker is still running or after it has already terminated.

What is captured
----------------

The current implementation captures:

- userland output that passes through PHP output buffering, such as ``echo``,
  ``print``, and ``printf``
- warnings and notices routed through the worker error handler
- exceptions caught by the worker execution wrapper

Reading partial output
----------------------

``Worker::getOutput()`` and ``Worker::getErrorOutput()`` may return partial
buffers before completion.

.. code-block:: php

   <?php

   foreach ($group->all() as $worker) {
       echo $worker->getOutput();
       echo $worker->getErrorOutput();
   }

Reading final output
--------------------

After ``wait()`` returns, the buffers exposed by the worker should reflect the
final captured output seen by the parent process.

.. code-block:: php

   <?php

   $group->wait();

   foreach ($group as $worker) {
       echo $worker->getOutput();
       echo $worker->getErrorOutput();
   }

What is not intercepted automatically
-------------------------------------

The current output transport does not redirect operating-system file
descriptors. As a result, the following are not captured automatically:

- direct writes to ``STDOUT`` through ``fwrite(STDOUT, ...)``
- direct writes to ``STDERR`` that bypass the worker wrapper
- any external program output unless you explicitly capture it inside the worker callback

Error handling behavior
-----------------------

Inside the worker callback:

- PHP errors that reach the configured worker error handler are written to the
  error output buffer
- uncaught throwables are converted into error output plus a non-zero exit code
- the callback return value is normalized into a process exit code

Exit code normalization
-----------------------

The worker normalizes callback results as follows:

.. list-table::
   :header-rows: 1

   * - Callback result
     - Normalized exit code
   * - integer ``0`` to ``255``
     - same value
   * - integer below ``0``
     - ``0``
   * - integer above ``255``
     - ``255``
   * - ``false``
     - ``1``
   * - any other non-integer value
     - ``0``

Best practices
--------------

- Prefer buffered userland output inside workers.
- Treat ``errorOutput`` as a structured diagnostics channel.
- If you need descriptor-level capture, add explicit redirection in your own worker code.
- If you stream a large volume of output, keep using manager- or group-level
  ``wait()`` so the parent continues draining worker sockets.
