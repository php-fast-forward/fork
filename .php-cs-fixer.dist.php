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

use FastForward\DevTools\Path\WorkingProjectPathResolver;
use PhpCsFixer\Config;
use PhpCsFixer\Finder;

require __DIR__ . '/vendor/autoload.php';

return (new Config())
    ->setRiskyAllowed(false)
    ->setFinder(Finder::create()->in(__DIR__)->exclude(WorkingProjectPathResolver::TOOLING_EXCLUDED_DIRECTORIES))
    ->setRules([
        'phpdoc_indent' => true,
        'phpdoc_order' => ['order' => ['param', 'return', 'throws']],
        'phpdoc_separation' => true,
        'phpdoc_trim' => true,
        'phpdoc_trim_consecutive_blank_line_separation' => true,
        'phpdoc_scalar' => true,
        'phpdoc_types' => true,
        'phpdoc_to_comment' => false,
        'phpdoc_add_missing_param_annotation' => true,
    ]);
