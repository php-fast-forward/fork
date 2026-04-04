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

namespace FastForward\Fork\Tests\Worker;

use FastForward\Fork\Tests\Support\ReflectsNonPublicMembers;
use FastForward\Fork\Tests\Support\SpyLogger;
use FastForward\Fork\Worker\WorkerOutputTransport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(WorkerOutputTransport::class)]
final class WorkerOutputTransportTest extends TestCase
{
    use ReflectsNonPublicMembers;

    /**
     * @return void
     */
    #[Test]
    public function itWillCaptureWrittenOutputAndErrorChunks(): void
    {
        $transport = WorkerOutputTransport::create();
        $logger = new SpyLogger();

        self::assertCount(2, $transport->getReadableStreams());

        $transport->writeOutput('stdout');
        $transport->writeErrorOutput('stderr');
        $transport->closeChildSide();
        $transport->drain(42, logger: $logger);
        $transport->drain(42, final: true, logger: $logger);

        self::assertSame('stdout', $transport->getOutput());
        self::assertSame('stderr', $transport->getErrorOutput());
        self::assertTrue($logger->hasRecord('debug', 'Worker stdout.'));
        self::assertTrue($logger->hasRecord('warning', 'Worker stderr.'));
    }

    /**
     * @return void
     */
    #[Test]
    public function activateParentSideWillCloseTheWriterStreamsAndMakeReadersNonBlocking(): void
    {
        $transport = WorkerOutputTransport::create();

        $transport->activateParentSide();

        self::assertNull($this->getNonPublicProperty($transport, 'outputWriter'));
        self::assertNull($this->getNonPublicProperty($transport, 'errorWriter'));
        self::assertFalse(stream_get_meta_data($this->getNonPublicProperty($transport, 'outputReader'))['blocked']);
        self::assertFalse(stream_get_meta_data($this->getNonPublicProperty($transport, 'errorReader'))['blocked']);
    }

    /**
     * @return void
     */
    #[Test]
    public function activateChildSideWillCloseTheReaderStreams(): void
    {
        $transport = WorkerOutputTransport::create();

        $transport->activateChildSide();
        $transport->drain(42, final: true);

        self::assertSame([], $transport->getReadableStreams());
        self::assertNull($this->getNonPublicProperty($transport, 'outputReader'));
        self::assertNull($this->getNonPublicProperty($transport, 'errorReader'));
    }

    /**
     * @return void
     */
    #[Test]
    public function drainWillRespectTheSelectedReadableStreams(): void
    {
        $transport = WorkerOutputTransport::create();
        $streams = $transport->getReadableStreams();

        $transport->writeOutput('stdout');
        $transport->writeErrorOutput('stderr');
        $transport->closeChildSide();
        $transport->drain(7, [$streams[0]]);

        self::assertSame('stdout', $transport->getOutput());
        self::assertSame('', $transport->getErrorOutput());

        $transport->drain(7, [$streams[1]], final: true);

        self::assertSame('stderr', $transport->getErrorOutput());
    }

    /**
     * @return void
     */
    #[Test]
    public function writeOperationsWillIgnoreEmptyChunks(): void
    {
        $transport = WorkerOutputTransport::create();

        $transport->writeOutput('');
        $transport->writeErrorOutput('');
        $transport->closeChildSide();
        $transport->drain(9, final: true);

        self::assertSame('', $transport->getOutput());
        self::assertSame('', $transport->getErrorOutput());
    }

    /**
     * @return void
     */
    #[Test]
    public function writeOperationsWillStopWhenTheTargetStreamCannotAcceptBytes(): void
    {
        $stream = fopen('php://temp', 'r');
        $transport = $this->instantiateWithoutConstructor(WorkerOutputTransport::class, [
            'output' => '',
            'errorOutput' => '',
            'outputReader' => null,
            'outputWriter' => $stream,
            'errorReader' => null,
            'errorWriter' => null,
        ]);

        $transport->writeOutput('stdout');

        self::assertSame('', $transport->getOutput());
        self::assertSame('', $transport->getErrorOutput());
    }

    /**
     * @return void
     */
    #[Test]
    public function activateMethodsWillIgnoreNullStreams(): void
    {
        $transport = $this->instantiateWithoutConstructor(WorkerOutputTransport::class, [
            'output' => '',
            'errorOutput' => '',
            'outputReader' => null,
            'outputWriter' => null,
            'errorReader' => null,
            'errorWriter' => null,
        ]);

        $transport->activateParentSide();
        $transport->activateChildSide();
        $transport->closeChildSide();

        self::assertSame([], $transport->getReadableStreams());
    }
}
