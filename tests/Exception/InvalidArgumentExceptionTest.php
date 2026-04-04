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

namespace FastForward\Fork\Tests\Exception;

use FastForward\Fork\Exception\ForkExceptionInterface;
use FastForward\Fork\Exception\InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvalidArgumentException::class)]
final class InvalidArgumentExceptionTest extends TestCase
{
    /**
     * @param callable $factory
     * @param string $expectedMessage
     *
     * @return void
     */
    #[Test]
    #[DataProvider('provideFactories')]
    public function namedConstructorsWillCreateLibraryExceptions(callable $factory, string $expectedMessage): void
    {
        $exception = $factory();

        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertInstanceOf(ForkExceptionInterface::class, $exception);
        self::assertSame($expectedMessage, $exception->getMessage());
    }

    /**
     * @return iterable
     */
    public static function provideFactories(): iterable
    {
        yield 'non-positive worker count' => [
            static fn(): InvalidArgumentException => InvalidArgumentException::forNonPositiveWorkerCount(0),
            'The worker count must be greater than zero, 0 given.',
        ];

        yield 'foreign worker' => [
            static fn(): InvalidArgumentException => InvalidArgumentException::forForeignWorker(42),
            'Worker 42 is not managed by this fork manager.',
        ];

        yield 'foreign worker group' => [
            InvalidArgumentException::forForeignWorkerGroup(...),
            'The provided worker group is not managed by this fork manager.',
        ];

        yield 'unsupported worker implementation' => [
            static fn(): InvalidArgumentException => InvalidArgumentException::forUnsupportedWorkerImplementation(
                'App\\Worker'
            ),
            'The worker implementation App\\Worker is not supported by this fork manager.',
        ];
    }
}
