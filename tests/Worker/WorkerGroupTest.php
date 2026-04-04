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

use FastForward\Fork\Manager\ForkManagerInterface;
use FastForward\Fork\Signal\Signal;
use FastForward\Fork\Worker\WorkerGroup;
use FastForward\Fork\Worker\WorkerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

#[CoversClass(WorkerGroup::class)]
final class WorkerGroupTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @return void
     */
    #[Test]
    public function itWillExposeWorkersIndexedByTheirProcessIdentifier(): void
    {
        $manager = $this->prophesize(ForkManagerInterface::class);
        $firstWorker = $this->prophesize(WorkerInterface::class);
        $firstWorker->getPid()
            ->willReturn(101);
        $firstWorker->isRunning()
            ->willReturn(true);
        $secondWorker = $this->prophesize(WorkerInterface::class);
        $secondWorker->getPid()
            ->willReturn(202);
        $secondWorker->isRunning()
            ->willReturn(false);
        $thirdWorker = $this->prophesize(WorkerInterface::class);
        $thirdWorker->getPid()
            ->willReturn(303);
        $thirdWorker->isRunning()
            ->willReturn(true);

        $group = new WorkerGroup(
            $manager->reveal(),
            $firstWorker->reveal(),
            $secondWorker->reveal(),
            $thirdWorker->reveal(),
        );

        self::assertSame($manager->reveal(), $group->getManager());
        self::assertSame([101, 202, 303], array_keys($group->all()));
        self::assertSame($firstWorker->reveal(), $group->get(101));
        self::assertNull($group->get(999));
        self::assertSame([101, 303], array_keys($group->getRunning()));
        self::assertSame([202], array_keys($group->getStopped()));
        self::assertSame([101, 202, 303], array_keys(iterator_to_array($group)));
        self::assertCount(3, $group);
    }

    /**
     * @return void
     */
    #[Test]
    public function waitWillDelegateToTheManager(): void
    {
        $manager = $this->prophesize(ForkManagerInterface::class);
        $worker = $this->prophesize(WorkerInterface::class);
        $worker->getPid()
            ->willReturn(101);
        $worker->isRunning()
            ->willReturn(true);

        $group = new WorkerGroup($manager->reveal(), $worker->reveal());

        $manager->wait(Argument::that(
            static fn(mixed $value): bool => $value instanceof WorkerGroup && $value->get(101) === $worker->reveal(),
        ))->shouldBeCalledOnce();

        $group->wait();
    }

    /**
     * @return void
     */
    #[Test]
    public function killWillDelegateToTheManagerUsingTheDefaultTerminationSignal(): void
    {
        $manager = $this->prophesize(ForkManagerInterface::class);
        $worker = $this->prophesize(WorkerInterface::class);
        $worker->getPid()
            ->willReturn(101);
        $worker->isRunning()
            ->willReturn(true);

        $group = new WorkerGroup($manager->reveal(), $worker->reveal());

        $manager->kill(Signal::Terminate, Argument::that(
            static fn(mixed $value): bool => $value instanceof WorkerGroup && $value->get(101) === $worker->reveal(),
        ))->shouldBeCalledOnce();

        $group->kill();
    }
}
