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

/**
 * Represents supported POSIX signals exposed by the public API.
 *
 * This enumeration provides a strongly-typed abstraction over common POSIX
 * signals used for process control and inter-process communication.
 *
 * Each case MUST map directly to its corresponding POSIX signal number.
 * Consumers MAY use this enum to improve readability and type safety when
 * sending or handling signals.
 *
 * Implementations interacting with system-level process control SHOULD rely on
 * these values to ensure consistency across environments.
 */
enum Signal: int
{
    /**
     * Terminal line hangup signal (SIGHUP).
     *
     * This signal is typically sent when a controlling terminal is closed.
     */
    case Hangup = \SIGHUP;

    /**
     * Interactive interrupt signal (SIGINT).
     *
     * This signal is usually triggered by user interaction (e.g., Ctrl+C).
     */
    case Interrupt = \SIGINT;

    /**
     * Interactive quit signal (SIGQUIT).
     *
     * This signal MAY trigger a core dump depending on the environment.
     */
    case Quit = \SIGQUIT;

    /**
     * Uncatchable termination signal (SIGKILL).
     *
     * This signal MUST NOT be caught, blocked, or ignored by the process.
     */
    case Kill = \SIGKILL;

    /**
     * User-defined application signal 1 (SIGUSR1).
     *
     * This signal MAY be used for custom application-level communication.
     */
    case User1 = \SIGUSR1;

    /**
     * User-defined application signal 2 (SIGUSR2).
     *
     * This signal MAY be used for custom application-level communication.
     */
    case User2 = \SIGUSR2;

    /**
     * Broken pipe signal (SIGPIPE).
     *
     * This signal is raised when writing to a pipe with no readers.
     */
    case Pipe = \SIGPIPE;

    /**
     * Alarm clock signal (SIGALRM).
     *
     * This signal is typically used for timer-based interruptions.
     */
    case Alarm = \SIGALRM;

    /**
     * Graceful termination signal (SIGTERM).
     *
     * This signal SHOULD be used to request a controlled shutdown.
     */
    case Terminate = \SIGTERM;

    /**
     * Child status changed signal (SIGCHLD).
     *
     * This signal is sent to a parent process when a child process changes state.
     */
    case Child = \SIGCHLD;

    /**
     * Continue-if-stopped signal (SIGCONT).
     *
     * This signal resumes a previously stopped process.
     */
    case Continue = \SIGCONT;

    /**
     * Process stop signal (SIGSTOP).
     *
     * This signal MUST stop the process and MUST NOT be caught or ignored.
     */
    case Stop = \SIGSTOP;

    /**
     * Terminal stop signal (SIGTSTP).
     *
     * This signal is typically triggered by user interaction (e.g., Ctrl+Z).
     */
    case TerminalStop = \SIGTSTP;

    /**
     * Returns the conventional shell exit status derived from this signal.
     *
     * According to POSIX conventions, when a process is terminated by a signal,
     * the exit status SHOULD be reported as 128 plus the signal number.
     *
     * @return int the computed exit status
     */
    public function exitStatus(): int
    {
        return 128 + $this->value;
    }
}
