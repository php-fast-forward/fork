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

namespace FastForward\Fork\Worker;

use Closure;
use FastForward\Fork\Exception\RuntimeException;
use Psr\Log\LoggerInterface;

use function fclose;
use function feof;
use function fwrite;
use function stream_get_contents;
use function stream_set_blocking;
use function stream_socket_pair;
use function substr;

/**
 * Manages the socket-based transport used to stream worker stdout and error output back to the parent.
 *
 * This component is responsible for:
 * - Creating independent socket pairs for stdout and stderr
 * - Coordinating parent/child stream ownership
 * - Streaming data between processes
 * - Accumulating output buffers
 *
 * Instances of this class MUST be created via the factory method to ensure
 * proper resource initialization.
 *
 * @internal
 */
final class WorkerOutputTransport
{
    /**
     * Accumulates stdout captured from the worker process.
     */
    private string $output = '';

    /**
     * Accumulates error output captured from the worker process.
     */
    private string $errorOutput = '';

    /**
     * Initializes the transport with reader/writer socket pairs.
     *
     * @param resource|null $outputReader reader socket used by the parent process for stdout
     * @param resource|null $outputWriter writer socket used by the child process for stdout
     * @param resource|null $errorReader reader socket used by the parent process for error output
     * @param resource|null $errorWriter writer socket used by the child process for error output
     */
    private function __construct(
        private $outputReader,
        private $outputWriter,
        private $errorReader,
        private $errorWriter,
    ) {}

    /**
     * Creates a fresh transport with independent stdout and error socket pairs.
     *
     * Both socket pairs MUST be successfully created. If allocation fails,
     * all partially allocated resources SHALL be released and an exception MUST be thrown.
     *
     * @return self a fully initialized transport instance
     *
     * @throws RuntimeException if socket allocation fails
     */
    public static function create(): self
    {
        $outputPair = stream_socket_pair(\STREAM_PF_UNIX, \STREAM_SOCK_STREAM, 0);
        $errorPair = stream_socket_pair(\STREAM_PF_UNIX, \STREAM_SOCK_STREAM, 0);

        // @codeCoverageIgnoreStart
        if (! \is_array($outputPair) || 2 !== \count($outputPair)) {
            throw RuntimeException::forWorkerOutputAllocationFailure();
        }

        // @codeCoverageIgnoreEnd

        // @codeCoverageIgnoreStart
        if (! \is_array($errorPair) || 2 !== \count($errorPair)) {
            fclose($outputPair[0]);
            fclose($outputPair[1]);

            throw RuntimeException::forWorkerOutputAllocationFailure();
        }

        // @codeCoverageIgnoreEnd

        return new self(
            outputReader: $outputPair[0],
            outputWriter: $outputPair[1],
            errorReader: $errorPair[0],
            errorWriter: $errorPair[1],
        );
    }

    /**
     * Activates the parent side of the transport.
     *
     * The parent process MUST close child-side writers and SHOULD switch
     * reader streams to non-blocking mode.
     */
    public function activateParentSide(): void
    {
        $this->closeStream($this->outputWriter);
        $this->closeStream($this->errorWriter);

        if (\is_resource($this->outputReader)) {
            stream_set_blocking($this->outputReader, false);
        }

        if (\is_resource($this->errorReader)) {
            stream_set_blocking($this->errorReader, false);
        }
    }

    /**
     * Activates the child side of the transport.
     *
     * The child process MUST close parent-side readers to prevent descriptor leaks.
     */
    public function activateChildSide(): void
    {
        $this->closeStream($this->outputReader);
        $this->closeStream($this->errorReader);
    }

    /**
     * Returns the currently readable transport streams.
     *
     * Only valid and open stream resources SHALL be returned.
     *
     * @return array<int, resource> list of readable streams
     */
    public function getReadableStreams(): array
    {
        $streams = [];

        if (\is_resource($this->outputReader)) {
            $streams[] = $this->outputReader;
        }

        if (\is_resource($this->errorReader)) {
            $streams[] = $this->errorReader;
        }

        return $streams;
    }

    /**
     * Drains readable data from the transport into internal buffers.
     *
     * The method MAY operate on a subset of streams if provided.
     * When finalization is requested, exhausted streams MUST be closed.
     *
     * @param int $workerPid PID associated with the drained worker
     * @param array<int, resource> $readableStreams optional subset of readable streams
     * @param bool $final whether this is the final drain operation
     * @param ?LoggerInterface $logger logger used for chunk-level output events
     */
    public function drain(
        int $workerPid,
        array $readableStreams = [],
        bool $final = false,
        ?LoggerInterface $logger = null,
    ): void {
        $this->output .= $this->readFrom(
            stream: $this->outputReader,
            readableStreams: $readableStreams,
            final: $final,
            level: 'debug',
            message: 'Worker stdout.',
            workerPid: $workerPid,
            close: function (): void {
                $this->closeStream($this->outputReader);
            },
            logger: $logger,
        );

        $this->errorOutput .= $this->readFrom(
            stream: $this->errorReader,
            readableStreams: $readableStreams,
            final: $final,
            level: 'warning',
            message: 'Worker stderr.',
            workerPid: $workerPid,
            close: function (): void {
                $this->closeStream($this->errorReader);
            },
            logger: $logger,
        );
    }

    /**
     * Writes a stdout chunk to the child transport.
     *
     * The operation MUST attempt to write the full chunk.
     *
     * @param string $chunk data to write to the standard output stream
     */
    public function writeOutput(string $chunk): void
    {
        $this->writeTo($this->outputWriter, $chunk);
    }

    /**
     * Writes an error-output chunk to the child transport.
     *
     * The operation MUST attempt to write the full chunk.
     *
     * @param string $chunk data to write to the error stream
     */
    public function writeErrorOutput(string $chunk): void
    {
        $this->writeTo($this->errorWriter, $chunk);
    }

    /**
     * Closes the child-side writer streams.
     *
     * This method SHOULD be invoked after callback execution completes.
     */
    public function closeChildSide(): void
    {
        $this->closeStream($this->outputWriter);
        $this->closeStream($this->errorWriter);
    }

    /**
     * Returns the accumulated stdout.
     *
     * @return string captured standard output
     */
    public function getOutput(): string
    {
        return $this->output;
    }

    /**
     * Returns the accumulated error output.
     *
     * @return string captured error output
     */
    public function getErrorOutput(): string
    {
        return $this->errorOutput;
    }

    /**
     * Writes a chunk to the provided stream until no more bytes can be written.
     *
     * @param resource|null $stream Target stream
     * @param string $chunk Data to write
     */
    private function writeTo($stream, string $chunk): void
    {
        if ('' === $chunk || ! \is_resource($stream)) {
            return;
        }

        $remaining = $chunk;

        while ('' !== $remaining) {
            $written = @fwrite($stream, $remaining);

            if (false === $written || 0 === $written) {
                break;
            }

            $remaining = substr($remaining, $written);
        }
    }

    /**
     * Reads data from a stream and returns the collected chunk.
     *
     * If the stream is not readable or not selected, the method SHALL return an empty string.
     * When finalization is enabled and EOF is reached, the stream MUST be closed.
     *
     * @param resource|null $stream source stream
     * @param array<int, resource> $readableStreams selected readable streams
     * @param bool $final whether this is the final drain operation
     * @param string $level log level
     * @param string $message log message
     * @param int $workerPid worker identifier
     * @param Closure $close closure responsible for closing the stream
     * @param ?LoggerInterface $logger logger used for output events
     *
     * @return string the read chunk
     */
    private function readFrom(
        $stream,
        array $readableStreams,
        bool $final,
        string $level,
        string $message,
        int $workerPid,
        Closure $close,
        ?LoggerInterface $logger = null,
    ): string {
        if (! \is_resource($stream)) {
            return '';
        }

        if ([] !== $readableStreams && ! \in_array($stream, $readableStreams, true)) {
            return '';
        }

        $chunk = stream_get_contents($stream);

        if (! \is_string($chunk) || '' === $chunk) {
            if ($final && feof($stream)) {
                $close();
            }

            return '';
        }

        $logger?->log($level, $message, [
            'worker_pid' => $workerPid,
            'output' => $chunk,
        ]);

        if ($final && feof($stream)) {
            $close();
        }

        return $chunk;
    }

    /**
     * Closes a stream resource and clears its reference.
     *
     * @param resource|null $stream stream to close
     */
    private function closeStream(&$stream): void
    {
        if (! \is_resource($stream)) {
            return;
        }

        fclose($stream);
        $stream = null;
    }
}
