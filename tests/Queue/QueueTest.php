<?php

namespace Smartling\Tests\Queue;

use PHPUnit\Framework\TestCase;
use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\Exception\SmartlingDbException;
use Smartling\Queue\Queue;
use Smartling\Tests\Traits\DbAlMock;
use Smartling\Tests\Traits\DummyLoggerMock;
use Smartling\Tests\Traits\InvokeMethodTrait;

class QueueTest extends TestCase
{
    use DbAlMock;
    use DummyLoggerMock;
    use InvokeMethodTrait;

    private $queue;
    private $dbal;

    public function setUp(): void
    {
        $this->dbal = $this->createMock(SmartlingToCMSDatabaseAccessWrapperInterface::class);
        $this->dbal->method('completeTableName')->willReturn(Queue::getTableName());
        $this->queue = new Queue();
    }

    /**
     * @dataProvider enqueueDataProvider
     * @param string $queue
     * @param array $value
     * @param string $expectedQuery
     */
    public function testEnqueue(string $queue, array $value, string $expectedQuery)
    {
        $this->dbal->expects(self::once())->method('query')->with($expectedQuery);

        $this->queue->setDbal($this->dbal);
        $this->queue->enqueue($value, $queue);
    }

    /**
     * @dataProvider enqueueFailsDataProvider
     * @param string $queue
     * @param array $value
     * @param string $expectedQuery
     */
    public function testEnqueueException(string $queue, array $value, string $expectedQuery)
    {
        $this->expectException(SmartlingDbException::class);
        $this->dbal->expects(self::once())->method('query')->with($expectedQuery)->willReturn(false);
        $this->queue->setDbal($this->dbal);
        $this->queue->enqueue($value, $queue);
    }

    /**
     * @param string $queueName
     * @param mixed  $value
     *
     * @return array
     */
    private function generatePositiveEnqueueDataSet(string $queueName, $value): array
    {
        return
            [
                $queueName,
                $value,
                vsprintf('INSERT IGNORE INTO `%s` (`queue`, `payload`, `payload_hash`) VALUES (\'%s\',\'%s\',\'%s\')',
                         [
                             Queue::getTableName(),
                             $queueName,
                             json_encode($value),
                             md5(json_encode($value)),
                         ]),
            ];
    }

    private function generatePositiveDequeueDataSet(string $queueName): array
    {
        return
            [
                $queueName,
                vsprintf('SELECT `id`, `queue`, `payload` FROM `%s` WHERE ( `queue` = \'%s\' ) LIMIT 0,1',
                         [
                             Queue::getTableName(),
                             $queueName,
                         ]),
            ];
    }

    private function generatePositivePurgeDataSet(string $queueName): array
    {
        return
            [
                $queueName,
                vsprintf('DELETE FROM `%s` WHERE ( `queue` = \'%s\' )',
                         [
                             Queue::getTableName(),
                             $queueName,
                         ]),
            ];
    }


    public function enqueueFailsDataProvider(): array
    {
        return [
            $this->generatePositiveEnqueueDataSet('upload', [1]),
            $this->generatePositiveEnqueueDataSet('upload', [1, 2, 3]),
        ];
    }

    public function enqueueDataProvider(): array
    {
        return [
            $this->generatePositiveEnqueueDataSet('upload', [1]),
            $this->generatePositiveEnqueueDataSet('upload', [1, 2, 3]),
        ];
    }

    /**
     * @dataProvider dequeueDataProvider
     * @param string $queue
     * @param string $expectedQuery
     */
    public function testDequeue(string $queue, string $expectedQuery)
    {
        $this->dbal->expects(self::once())->method('fetch')->with($expectedQuery, "ARRAY_A")->willReturn([]);
        $this->queue->setDbal($this->dbal);
        $this->queue->dequeue($queue);
    }

    public function dequeueDataProvider(): array
    {
        return [
            $this->generatePositiveDequeueDataSet('upload'),
            $this->generatePositiveDequeueDataSet('download'),
        ];
    }

    /**
     * @dataProvider purgeDataProvider
     *
     * @param string $queueName
     * @param string $expectedQuery
     */
    public function testPurge(string $queueName, string $expectedQuery)
    {
        $this->dbal->expects(self::once())->method('query')->with($expectedQuery);
        $this->queue->setDbal($this->dbal);
        $this->queue->purge($queueName);
    }

    public function purgeDataProvider(): array
    {
        return [
            $this->generatePositivePurgeDataSet('upload'),
        ];
    }

    /**
     * @dataProvider statsDataProvider
     * @param string $expectedQuery
     */
    public function testStats(string $expectedQuery)
    {
        $this->dbal->expects(self::once())->method('fetch')->with($expectedQuery, "ARRAY_A")->willReturn([]);
        $this->queue->setDbal($this->dbal);
        $this->queue->stats();
    }

    public function statsDataProvider(): array
    {
        return [
            [
                vsprintf('SELECT `queue`, count(`id`) AS `num` FROM `%s` GROUP BY `queue`', [Queue::getTableName()]),
            ],
        ];
    }
}
