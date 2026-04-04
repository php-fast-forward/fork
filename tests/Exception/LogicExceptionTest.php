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
use FastForward\Fork\Exception\LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LogicException::class)]
final class LogicExceptionTest extends TestCase
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

        self::assertInstanceOf(LogicException::class, $exception);
        self::assertInstanceOf(ForkExceptionInterface::class, $exception);
        self::assertSame($expectedMessage, $exception->getMessage());
    }

    /**
     * @return iterable
     */
    public static function provideFactories(): iterable
    {
        yield 'fork from worker process' => [
            LogicException::forForkFromWorkerProcess(...),
            'Forking from a worker process is not supported by this manager instance. '
            . 'Create a new manager inside the worker to manage a nested process tree.',
        ];

        yield 'worker waiting on itself' => [
            static fn(): LogicException => LogicException::forWorkerWaitingOnItself(42),
            'Worker 42 cannot wait on itself.',
        ];
    }
}
