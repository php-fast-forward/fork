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

use ReflectionClass;
use ReflectionMethod;

trait ReflectsNonPublicMembers
{
    /**
     * @param string $className
     * @param array $properties
     *
     * @return object
     */
    private function instantiateWithoutConstructor(string $className, array $properties = []): object
    {
        $reflection = new ReflectionClass($className);
        $instance = $reflection->newInstanceWithoutConstructor();

        foreach ($properties as $property => $value) {
            $reflection->getProperty($property)
                ->setValue($instance, $value);
        }

        return $instance;
    }

    /**
     * @param object $object
     * @param string $method
     * @param mixed $arguments
     *
     * @return mixed
     */
    private function invokeNonPublicMethod(object $object, string $method, mixed ...$arguments): mixed
    {
        $reflection = new ReflectionMethod($object, $method);

        return $reflection->invoke($object, ...$arguments);
    }

    /**
     * @param object $object
     * @param string $property
     * @param mixed $value
     *
     * @return void
     */
    private function setNonPublicProperty(object $object, string $property, mixed $value): void
    {
        $reflection = new ReflectionClass($object);
        $reflection->getProperty($property)
            ->setValue($object, $value);
    }

    /**
     * @param object $object
     * @param string $property
     *
     * @return mixed
     */
    private function getNonPublicProperty(object $object, string $property): mixed
    {
        $reflection = new ReflectionClass($object);

        return $reflection->getProperty($property)
            ->getValue($object);
    }
}
