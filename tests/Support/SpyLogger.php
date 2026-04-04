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

namespace FastForward\Fork\Tests\Support;

use Stringable;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

final class SpyLogger extends AbstractLogger
{
    public array $records = [];

    /**
     * @param mixed $level
     * @param string|Stringable $message
     * @param array $context
     *
     * @return void
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    /**
     * @param string $level
     * @param string $message
     *
     * @return bool
     */
    public function hasRecord(string $level, string $message): bool
    {
        foreach ($this->records as $record) {
            if ($record['level'] === $level && $record['message'] === $message) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $level
     *
     * @return array
     */
    public function recordsByLevel(string $level): array
    {
        return array_values(array_filter(
            $this->records,
            static fn(array $record): bool => $record['level'] === $level,
        ));
    }

    /**
     * @param string $message
     *
     * @return bool
     */
    public function hasInfo(string $message): bool
    {
        return $this->hasRecord(LogLevel::INFO, $message);
    }

    /**
     * @param string $message
     *
     * @return bool
     */
    public function hasWarning(string $message): bool
    {
        return $this->hasRecord(LogLevel::WARNING, $message);
    }
}
