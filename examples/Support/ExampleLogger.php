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

use Stringable;
use Psr\Log\AbstractLogger;

use function json_encode;
use function printf;

/**
 * Prints PSR-3 log entries to the console for the logger example.
 */
final class ExampleLogger extends AbstractLogger
{
    /**
     * {@inheritDoc}
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        printf(
            "[%s] %s %s\n",
            $level,
            (string) $message,
            json_encode($context, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR),
        );
    }
}
