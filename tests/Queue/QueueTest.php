<?php

namespace Smartling\Tests\Queue;

use PHPUnit\Framework\TestCase;
use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\Exception\SmartlingDbException;
use Smartling\Queue\Queue;
use Smartling\Queue\QueueInterface;
use Smartling\Tests\Traits\DbAlMock;
use Smartling\Tests\Traits\DummyLoggerMock;
use Smartling\Tests\Traits\InvokeMethodTrait;


/**
 * Class QueueTest
 * Test class for \Smartling\Queue\Queue
 * @package Smartling\Tests\Queue
 * @covers  \Smartling\Queue\Queue
 */
class QueueTest extends TestCase
{

    use DbAlMock;
    use DummyLoggerMock;
    use InvokeMethodTrait;

    //region Fields Definitions
    /**
     * @var QueueInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $queue;

    /**
     * @var SmartlingToCMSDatabaseAccessWrapperInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $dbal;

    /**
     * @return QueueInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    public function getQueue()
    {
        return $this->queue;
    }

    /**
     * @param QueueInterface|\PHPUnit_Framework_MockObject_MockObject $queue
     */
    public function setQueue($queue)
    {
        $this->queue = $queue;
    }

    /**
     * @return SmartlingToCMSDatabaseAccessWrapperInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    public function getDbal()
    {
        return $this->dbal;
    }

    /**
     * @param SmartlingToCMSDatabaseAccessWrapperInterface|\PHPUnit_Framework_MockObject_MockObject $dbal
     */
    public function setDbal($dbal)
    {
        $this->dbal = $dbal;
    }
    //endregion

    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp(): void
    {
        $this->setDbal($this->mockDbAl());

        $queue = new Queue();

        $queue->setDbal($this->getDbal());
        $this->setQueue($queue);

    }

    /**
     * @covers       \Smartling\Queue\Queue::enqueue()
     * @dataProvider enqueueDataProvider
     *
     * @param string $queue
     * @param string $value
     * @param string $expectedQuery
     */
    public function testEnqueue($queue, $value, $expectedQuery)
    {
        $db = $this->getDbal();
        $db->expects(self::any())->method('completeTableName')->withAnyParameters()->willReturn(Queue::getTableName());
        $db->expects(self::any())->method('query')->with($expectedQuery);
        $this->getQueue()->enqueue($value, $queue);
    }

    /**
     * @covers       \Smartling\Queue\Queue::enqueue()
     * @dataProvider enqueueFailsDataProvider
     *
     * @param string $queue
     * @param string $value
     * @param string $expectedQuery
     */
    public function testEnqueueException($queue, $value, $expectedQuery)
    {
        $this->expectException(SmartlingDbException::class);
        $db = $this->getDbal();
        $db->expects(self::any())->method('completeTableName')->withAnyParameters()->willReturn(Queue::getTableName());
        $db->expects(self::any())->method('query')->with($expectedQuery)->willReturn(false);
        $this->getQueue()->enqueue($value, $queue);
    }


    /**
     * @param string $queueName
     * @param mixed  $value
     *
     * @return array
     */
    private function generatePositiveEnqueueDataSet($queueName, $value)
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

    /**
     * @param string $queueName
     *
     * @return array
     */
    private function generatePositiveDequeueDataSet($queueName)
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

    /**
     * @param string $queueName
     *
     * @return array
     */
    private function generatePositivePurgeDataSet($queueName)
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


    public function enqueueFailsDataProvider()
    {
        return [
            $this->generatePositiveEnqueueDataSet('upload', [1]),
            $this->generatePositiveEnqueueDataSet('upload', [1, 2, 3]),
        ];
    }

    public function enqueueDataProvider()
    {
        return [
            $this->generatePositiveEnqueueDataSet('upload', [1]),
            $this->generatePositiveEnqueueDataSet('upload', [1, 2, 3]),
            $this->generatePositiveEnqueueDataSet('upload', [new \stdClass('f')]),
        ];
    }

    /**
     * @covers       \Smartling\Queue\Queue::dequeue()
     * @dataProvider dequeueDataProvider
     *
     * @param string $queue
     * @param string $expectedQuery
     */
    public function testDequeue($queue, $expectedQuery)
    {
        $db = $this->getDbal();
        $db->expects(self::any())->method('completeTableName')->withAnyParameters()->willReturn(Queue::getTableName());
        $db->expects(self::any())->method('fetch')->with($expectedQuery, \ARRAY_A)->willReturn([]);
        $this->getQueue()->dequeue($queue);
    }

    public function dequeueDataProvider()
    {
        return [
            $this->generatePositiveDequeueDataSet('upload'),
            $this->generatePositiveDequeueDataSet('download'),
        ];
    }

    /**
     * @covers       \Smartling\Queue\Queue::purge()
     * @dataProvider purgeDataProvider
     *
     * @param string $queueName
     * @param string $expectedQuery
     */
    public function testPurge($queueName, $expectedQuery)
    {
        $db = $this->getDbal();
        $db->expects(self::any())->method('completeTableName')->withAnyParameters()->willReturn(Queue::getTableName());
        $db->expects(self::any())->method('query')->with($expectedQuery);
        $this->getQueue()->purge($queueName);
    }

    public function purgeDataProvider()
    {
        return [
            $this->generatePositivePurgeDataSet('upload'),
            $this->generatePositivePurgeDataSet('download'),
            [
                null,
                vsprintf('DELETE FROM `%s`', [Queue::getTableName()]),
            ],
        ];
    }

    /**
     * @covers       \Smartling\Queue\Queue::stats()
     * @dataProvider statsDataProvider
     *
     * @param string $expectedQuery
     */
    public function testStats($expectedQuery)
    {
        $db = $this->getDbal();
        $db->expects(self::any())->method('completeTableName')->withAnyParameters()->willReturn(Queue::getTableName());
        $db->expects(self::any())->method('fetch')->with($expectedQuery, \ARRAY_A)->willReturn([]);
        $this->getQueue()->stats();
    }


    public function statsDataProvider()
    {
        return [
            [
                vsprintf('SELECT `queue`, count(`id`) AS `num` FROM `%s` GROUP BY `queue`', [Queue::getTableName()]),
            ],
        ];
    }
}