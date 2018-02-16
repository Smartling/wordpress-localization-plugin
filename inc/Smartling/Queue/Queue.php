<?php

namespace Smartling\Queue;

use Smartling\Base\SmartlingEntityAbstract;
use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\Exception\SmartlingDbException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\QueryBuilder\Condition\Condition;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBlock;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBuilder;
use Smartling\Helpers\QueryBuilder\QueryBuilder;

class Queue extends SmartlingEntityAbstract implements QueueInterface
{
    /**
     * @var SmartlingToCMSDatabaseAccessWrapperInterface
     */
    private $dbal;

    /**
     * @return SmartlingToCMSDatabaseAccessWrapperInterface
     */
    public function getDbal()
    {
        return $this->dbal;
    }

    /**
     * @param SmartlingToCMSDatabaseAccessWrapperInterface $dbal
     */
    public function setDbal(SmartlingToCMSDatabaseAccessWrapperInterface $dbal)
    {
        $this->dbal = $dbal;
    }

    public static function getFieldDefinitions()
    {
        return [
            'id'           => static::DB_TYPE_U_BIGINT . ' ' . static::DB_TYPE_INT_MODIFIER_AUTOINCREMENT,
            'queue'        => static::DB_TYPE_STRING_64,
            'payload'      => static::DB_TYPE_STRING_TEXT,
            'payload_hash' => static::DB_TYPE_HASH_MD5,
        ];
    }

    public static function getFieldLabels()
    {
        return [
            'id'      => __('Job Id'),
            'queue'   => __('Queue Name'),
            'payload' => __('Data'),
        ];
    }

    public static function getSortableFields()
    {
        return [
            'id',
            'queue',
        ];
    }

    public static function getIndexes()
    {
        return [
            [
                'type'    => 'primary',
                'columns' => ['id'],
            ],
            [
                'type'    => 'unique',
                'columns' => ['queue', 'payload_hash'],
            ],
        ];
    }

    public static function getTableName()
    {
        return 'smartling_queue';
    }

    private function testValueToBeEnqueued(array $value)
    {
        $res = $value === json_decode(json_encode($value), true);
        if (!$res) {
            $message = vsprintf('Error: $value !== json_decode(json_encode($value)). Encoded string: %s', [
              json_encode($value),
            ]);
            $this->logger->error($message);
        }

        return $res;
    }

    private function testQueue($queue)
    {
        return is_string($queue);
    }

    private function getRealTableName()
    {
        return $this->getDbal()->completeTableName(static::getTableName());
    }

    private function set($value, $queue)
    {
        $query = QueryBuilder::buildInsertQuery(
            $this->getRealTableName(),
            [
                'queue'        => $queue,
                'payload'      => $value,
                'payload_hash' => md5($value),
            ],
            true
        );

        $result = $this->getDbal()->query($query);

        if (false === $result) {
            $message = vsprintf('Error while adding element to queue: %s', [
                $this->getDbal()->getLastErrorMessage(),
            ]);
            $this->logger->error($message);
            throw new SmartlingDbException($message);
        }
    }

    private function delete($id, $queue)
    {
        $id = (int)$id;

        $conditionBlock = ConditionBlock::getConditionBlock();

        $conditionBlock->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, 'queue', [
            $queue,
        ]));

        $conditionBlock->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, 'id', [
            $id,
        ]));

        $query = QueryBuilder::buildDeleteQuery($this->getRealTableName(), $conditionBlock, ['limit' => 1]);

        $result = $this->getDbal()->query($query);

        if (false === $result) {
            $message = vsprintf('Error while deleting element from queue: %s; Query: %s', [
                $this->getDbal()->getLastErrorMessage(),
                $query
            ]);
            $this->logger->error($message);
            throw new SmartlingDbException($message);
        }

        return $result;

    }

    private function get($queue)
    {
        $conditionBlock = ConditionBlock::getConditionBlock();

        $conditionBlock->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, 'queue', [
            $queue,
        ]));

        $query = QueryBuilder::buildSelectQuery(
            $this->getRealTableName(),
            [
                'id',
                'queue',
                'payload',
            ],
            $conditionBlock,
            [],
            [
                'limit' => 1,
                'page'  => 1,
            ]);

        $result = $this->getDbal()->fetch($query, \ARRAY_A);

        if (!is_array($result)) {
            $message = vsprintf('Error while getting element from queue: %s', [
                $this->getDbal()->getLastErrorMessage(),
            ]);
            $this->logger->error($message);
            throw new SmartlingDbException($message);
        }

        return $result;
    }

    /**
     * Adds an array to the queue
     *
     * @param array  $value
     * @param string $queue
     */
    public function enqueue(array $value, $queue)
    {
        if ($this->testValueToBeEnqueued($value) && $this->testQueue($queue)) {
            $encodedValue = json_encode($value);
            $this->set($encodedValue, $queue);
        }
    }

    private function extractValue(array $array, $key)
    {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        } else {
            $message = vsprintf('Expected array %s to have key %s but it doesn\'t.', [
                var_export($array, true),
                var_export($key, true),
            ]);
            throw new \LogicException($message);
        }
    }

    /**
     * @param string $queue
     *
     * @return array|false if queue is empty
     */
    public function dequeue($queue)
    {
        if ($this->testQueue($queue)) {
            $result = $this->get($queue);

            if (is_array($result) && 1 === count($result)) {
                $row = ArrayHelper::first($result);

                $id = $this->extractValue($row, 'id');
                $payload = $this->extractValue($row, 'payload');

                $value = json_decode($payload, true);

                $this->delete($id, $queue);

                return $value;
            }
        }

        return false;
    }

    /**
     * @param string|null $queue
     *
     * @throws SmartlingDbException
     */
    public function purge($queue = null)
    {
        $pageOptions = null;

        $condition = null;

        if (null !== $queue && is_string($queue)) {
            $condition = ConditionBlock::getConditionBlock();
            $condition->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, 'queue', [$queue]));
        }

        $query = QueryBuilder::buildDeleteQuery($this->getRealTableName(), $condition, $pageOptions);

        $result = $this->getDbal()->query($query);

        if (false === $result) {
            if (null !== $queue) {
                $template = 'Error while purging all elements from queue=%s. Message: %s';
                $message = vsprintf($template, [
                    $queue,
                    $this->getDbal()->getLastErrorMessage(),
                ]);
            } else {
                $template = 'Error while purging all elements from all queues. Message: %s';
                $message = vsprintf($template, [$this->getDbal()->getLastErrorMessage()]);
            }

            $this->logger->error($message);
            throw new SmartlingDbException($message);
        }
    }

    /**
     * @return array['queue' => elements_count]
     */
    public function stats()
    {
        $query = QueryBuilder::buildSelectQuery(
            $this->getRealTableName(),
            [
                'queue',
                ['count(`id`)' => 'num']],
                null,
                [],
                null,
                ['queue']
        );

        $result = $this->getDbal()->fetch($query, \ARRAY_A);
        $output = [];
        foreach ($result as $row) {
            $output[$row['queue']] = (int)$row['num'];
        }

        return $output;
    }
}
