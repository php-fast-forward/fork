<?php

declare(strict_types=1);

/**
 * This file is part of fast-forward/fork.
 *
 * This source file is subject to the license bundled
 * with this source code in the file LICENSE.
 *
 * @copyright Copyright (c) 2026 Felipe Sayão Lobato Abreu <github@mentordosnerds.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 *
 * @see       https://github.com/php-fast-forward/fork
 * @see       https://github.com/php-fast-forward
 * @see       https://datatracker.ietf.org/doc/html/rfc2119
 */

namespace FastForward\Fork\Signal;

use FastForward\Fork\Manager\ForkManagerInterface;

/**
 * Propagates termination-oriented signals to workers managed by the master process.
 *
 * This handler is intended to provide a default shutdown strategy for signals that
 * require worker termination or graceful process unwinding.
 */
final class DefaultSignalHandler implements SignalHandlerInterface
{
    /**
     * Defines the default signals subscribed by the handler.
     *
     * @var array<int, Signal> signals listened to by default
     */
    public const array DEFAULT_SIGNALS = [Signal::Interrupt, Signal::Terminate, Signal::Quit];

    /**
     * Indicates whether the handler is already processing a signal.
     *
     * This flag prevents re-entrant shutdown handling when multiple signals are
     * received during an ongoing termination sequence.
     */
    private bool $handling = false;

    /**
     * Creates a new default signal handler instance.
     *
     * @param array<int, Signal> $signals signals to subscribe to on the manager process
     * @param bool $waitForWorkers whether the master process should wait for workers after propagation
     * @param bool $exitOnSignal whether the master process should exit after handling the signal
     * @param Signal $escalationSignal signal used when a second signal interrupts an in-progress shutdown
     */
    public function __construct(
        private readonly array $signals = self::DEFAULT_SIGNALS,
        private readonly bool $waitForWorkers = true,
        private readonly bool $exitOnSignal = true,
        private readonly Signal $escalationSignal = Signal::Kill,
    ) {}

    /**
     * Returns the signals subscribed by this handler.
     *
     * @return array<int, Signal> the configured list of subscribed signals
     */
    public function signals(): array
    {
        return $this->signals;
    }

    /**
     * Handles a received signal for the current manager context.
     *
     * When invoked in the master process, the handler propagates an appropriate
     * signal to managed workers. If signal handling is already in progress, the
     * escalation signal is sent instead to accelerate shutdown.
     *
     * @param ForkManagerInterface $manager the fork manager receiving the signal
     * @param Signal $signal the signal that triggered the handler
     */
    public function __invoke(ForkManagerInterface $manager, Signal $signal): void
    {
        $mustExit = $manager->isWorker() || $this->exitOnSignal;

        if ($this->handling) {
            $this->terminateWorkers($manager, $this->escalationSignal);

            // @codeCoverageIgnoreStart
            if ($mustExit) {
                exit($signal->exitStatus());
            }

            // @codeCoverageIgnoreEnd

            return;
        }

        $this->handling = true;

        try {
            $this->terminateWorkers($manager, $this->propagationSignal($signal));

            if ($this->waitForWorkers && $manager->isMaster()) {
                $manager->wait();
            }
        } finally {
            $this->handling = false;
        }

        // @codeCoverageIgnoreStart
        if ($mustExit) {
            exit($signal->exitStatus());
        }

        // @codeCoverageIgnoreEnd
    }

    /**
     * Sends the provided signal to all workers managed by the master process.
     *
     * No action is taken when the current process is not the master process.
     *
     * @param ForkManagerInterface $manager the manager responsible for worker coordination
     * @param Signal $signal the signal to send to managed workers
     */
    private function terminateWorkers(ForkManagerInterface $manager, Signal $signal): void
    {
        if (! $manager->isMaster()) {
            return;
        }

        $manager->kill($signal);
    }

    /**
     * Converts interactive termination signals to the signal used for worker propagation.
     *
     * Interrupt, quit, and terminate signals are normalized to a graceful termination
     * request for workers. Any other signal is returned unchanged.
     *
     * @param Signal $signal the original signal received by the handler
     *
     * @return Signal the normalized signal to propagate to workers
     */
    private function propagationSignal(Signal $signal): Signal
    {
        return match ($signal) {
            Signal::Interrupt, Signal::Quit, Signal::Terminate => Signal::Terminate,
            default => $signal,
        };
    }
}
