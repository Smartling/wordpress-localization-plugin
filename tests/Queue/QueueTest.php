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

    private ?SmartlingToCMSDatabaseAccessWrapperInterface $dbal = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        defined('ARRAY_A') || define('ARRAY_A', 'ARRAY_A');
        defined('OBJECT') || define('OBJECT', 'OBJECT');
    }

    public function setUp(): void
    {
        $this->dbal = $this->createMock(SmartlingToCMSDatabaseAccessWrapperInterface::class);
        $this->dbal->method('completeTableName')->willReturn(Queue::getTableName());
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

        (new Queue($this->dbal))->enqueue($value, $queue);
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

        (new Queue($this->dbal))->enqueue($value, $queue);
    }

    private function generatePositiveEnqueueDataSet(string $queueName, array $value): array
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

    public function testDeleteQuery()
    {
        $tableName = Queue::getTableName();
        $queueName = 'test';
        $itemId = 1313;
        $payload = ['status' => 'OK'];
        $db = $this->createMock(SmartlingToCMSDatabaseAccessWrapperInterface::class);
        $db->method('completeTableName')->willReturnArgument(0);
        $db->expects($this->once())->method('fetch')
            ->with("SELECT `id`, `queue`, `payload` FROM `$tableName` WHERE ( `queue` = '$queueName' ) LIMIT 0,1")
            ->willReturn([['id' => $itemId, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR)]]);
        $db->expects($this->once())->method('query')
            ->with("DELETE FROM `$tableName` WHERE ( `queue` = '$queueName' AND `id` = '$itemId' ) LIMIT 1");

        (new Queue($db))->dequeue($queueName);
    }

    /**
     * @dataProvider dequeueDataProvider
     */
    public function testDequeue(string $queue, string $expectedQuery)
    {
        $this->dbal->expects(self::once())->method('fetch')->with($expectedQuery, \ARRAY_A)->willReturn([]);

        (new Queue($this->dbal))->dequeue($queue);
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
     */
    public function testPurge(string $queueName, string $expectedQuery)
    {
        $this->dbal->expects(self::once())->method('query')->with($expectedQuery);

        (new Queue($this->dbal))->purge($queueName);
    }

    public function purgeDataProvider(): array
    {
        return [
            $this->generatePositivePurgeDataSet('upload'),
        ];
    }

    /**
     * @dataProvider statsDataProvider
     */
    public function testStats(string $expectedQuery)
    {
        $this->dbal->expects(self::once())->method('fetch')->with($expectedQuery, \ARRAY_A)->willReturn([]);

        (new Queue($this->dbal))->stats();
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
