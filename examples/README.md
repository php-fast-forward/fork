# Examples

The examples are now ordered from the most common case to the most specific scenarios.

- `php examples/01-basic-fork.php`: creates a simple group of workers, waits for the group, and prints the final state of each worker.

- `php examples/02-inspect-worker-group.php`: shows how to use `all()`, `get(pid)`, `getRunning()`, and `getStopped()` during execution.

- `php examples/03-manager-wait-all.php`: creates more than one group and demonstrates `ForkManager::wait()` without arguments to reconcile all remaining workers.

- `php examples/04-stream-worker-output.php`: demonstrates reading a partial `stdout` before the worker finishes.

- `php examples/05-capture-worker-errors.php`: demonstrates warnings and exceptions captured in `errorOutput`.

- `php examples/06-group-kill.php`: demonstrates how to stop an entire group of long-running workers.

- `php examples/07-targeted-manager-control.php`: demonstrates `ForkManager::kill()` and `ForkManager::wait()` with groups and individual workers in the same flow.

- `php examples/08-logger-integration.php`: demonstrates how to inject a PSR-3 logger to track the lifecycle and output of workers.

- `php examples/09-default-signal-handler.php`: demonstrates the `DefaultSignalHandler` propagating signals from the master to the workers.

- `php examples/10-verify-library-behavior.php`: runs a deterministic smoke test to validate the main library flow.

Auxiliary files:

- `examples/bootstrap.php`: loads the project autoload and the classes shared by the examples.

- `examples/Support/`: contains utilities for printing, waiting, logging, and verification.