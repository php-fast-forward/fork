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

namespace FastForward\Fork\Tests\Signal;

use FastForward\Fork\Signal\Signal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Signal::class)]
final class SignalTest extends TestCase
{
    /**
     * @param Signal $signal
     *
     * @return void
     */
    #[Test]
    #[DataProvider('provideSignals')]
    public function exitStatusWillBeCalculatedFromTheNativeSignalValue(Signal $signal): void
    {
        self::assertSame(128 + $signal->value, $signal->exitStatus());
    }

    /**
     * @return iterable
     */
    public static function provideSignals(): iterable
    {
        foreach (Signal::cases() as $signal) {
            yield $signal->name => [$signal];
        }
    }
}
