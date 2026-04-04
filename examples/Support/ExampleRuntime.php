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

namespace FastForward\Fork\Examples\Support;

use function microtime;
use function usleep;

/**
 * Provides small runtime helpers shared by the examples.
 */
final class ExampleRuntime
{
    /**
     * Waits until the provided condition returns true or the timeout expires.
     *
     * @param callable $condition
     * @param float $timeoutSeconds
     * @param int $sleepMicroseconds
     */
    public static function waitUntil(
        callable $condition,
        float $timeoutSeconds = 1.0,
        int $sleepMicroseconds = 20_000,
    ): bool {
        $deadline = microtime(true) + $timeoutSeconds;

        do {
            if ($condition()) {
                return true;
            }

            usleep($sleepMicroseconds);
        } while (microtime(true) < $deadline);

        return (bool) $condition();
    }
}
