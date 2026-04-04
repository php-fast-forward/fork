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

namespace FastForward\Fork\Exception;

use Throwable;

/**
 * Represents the base contract for all exceptions thrown by the fork library.
 *
 * All custom exceptions defined by the library MUST implement this interface
 * to allow consistent and type-safe exception handling across the system.
 *
 * Consumers MAY use this interface to catch all library-specific exceptions
 * without interfering with unrelated runtime exceptions.
 *
 * Implementations SHOULD NOT extend this interface outside the library boundary
 * unless they are explicitly designed to integrate with the fork ecosystem.
 */
interface ForkExceptionInterface extends Throwable {}
