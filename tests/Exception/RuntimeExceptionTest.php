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
use FastForward\Fork\Exception\RuntimeException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RuntimeException::class)]
final class RuntimeExceptionTest extends TestCase
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

        self::assertInstanceOf(RuntimeException::class, $exception);
        self::assertInstanceOf(ForkExceptionInterface::class, $exception);
        self::assertSame($expectedMessage, $exception->getMessage());
    }

    /**
     * @return iterable
     */
    public static function provideFactories(): iterable
    {
        yield 'unsupported forking' => [
            RuntimeException::forUnsupportedForking(...),
            'Process forking is not supported in the current runtime.',
        ];

        yield 'unable to fork worker' => [
            RuntimeException::forUnableToForkWorker(...),
            'Unable to fork a new worker process.',
        ];

        yield 'worker wait failure' => [
            static fn(): RuntimeException => RuntimeException::forWorkerWaitFailure(42, 'Interrupted system call'),
            'Unable to wait for worker 42: Interrupted system call',
        ];

        yield 'undetectable process identifier' => [
            RuntimeException::forUndetectableProcessIdentifier(...),
            'Unable to detect the current process identifier.',
        ];

        yield 'worker output allocation failure' => [
            RuntimeException::forWorkerOutputAllocationFailure(...),
            'Unable to allocate transport for worker output.',
        ];

        yield 'workers wait failure' => [
            static fn(): RuntimeException => RuntimeException::forWorkersWaitFailure('No child processes'),
            'Unable to wait for workers: No child processes',
        ];
    }
}
