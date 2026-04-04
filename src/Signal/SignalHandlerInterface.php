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
 * Defines the contract for manager-owned signal handlers.
 *
 * Implementations MUST define which signals they subscribe to and MUST provide
 * a callable handler capable of reacting to those signals within the context of
 * a fork manager.
 *
 * Signal handlers SHOULD be idempotent and MUST be safe to execute multiple
 * times, as signal delivery MAY occur repeatedly or concurrently depending on
 * the runtime environment.
 *
 * Implementations MAY normalize or reinterpret signals before acting upon them,
 * provided that the resulting behavior remains predictable for consumers.
 *
 * The key words "MUST", "MUST NOT", "SHALL", "SHALL NOT", "SHOULD",
 * "SHOULD NOT", "RECOMMENDED", "MAY", and "OPTIONAL" are to be interpreted as
 * described in RFC 2119.
 */
interface SignalHandlerInterface
{
    /**
     * Returns the signals handled by this handler.
     *
     * Implementations MUST return a non-empty list of signals that the manager
     * will subscribe to on behalf of this handler.
     *
     * @return array<int, Signal> the list of handled signals
     */
    public function signals(): array;

    /**
     * Handles a signal for the provided manager.
     *
     * Implementations MUST execute the handling logic for the given signal in
     * the context of the provided manager. The handler MAY trigger side effects
     * such as propagating signals to workers, performing cleanup, or terminating
     * the process.
     *
     * @param ForkManagerInterface $manager manager that received the signal
     * @param Signal $signal signal that was received or normalized by the manager
     *
     * @return void
     */
    public function __invoke(ForkManagerInterface $manager, Signal $signal): void;
}
