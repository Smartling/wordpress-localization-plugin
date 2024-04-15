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
    public function __construct(private SmartlingToCMSDatabaseAccessWrapperInterface $dbal)
    {
        parent::__construct();
    }

    public static function getFieldDefinitions(): array
    {
        return [
            'id'           => static::DB_TYPE_U_BIGINT . ' ' . static::DB_TYPE_INT_MODIFIER_AUTOINCREMENT,
            'queue'        => static::DB_TYPE_STRING_64,
            'payload'      => static::DB_TYPE_STRING_TEXT,
            'payload_hash' => static::DB_TYPE_HASH_MD5,
        ];
    }

    public static function getFieldLabels(): array
    {
        return [
            'id'      => __('Job Id'),
            'queue'   => __('Queue Name'),
            'payload' => __('Data'),
        ];
    }

    public static function getSortableFields(): array
    {
        return [
            'id',
            'queue',
        ];
    }

    public static function getIndexes(): array
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

    public static function getTableName(): string
    {
        return 'smartling_queue';
    }

    private function testValueToBeEnqueued(array $value): bool
    {
        $res = $value === json_decode(json_encode($value, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        if (!$res) {
            $message = vsprintf('Error: $value !== json_decode(json_encode($value)). Encoded string: %s', [
                json_encode($value, JSON_THROW_ON_ERROR),
            ]);
            $this->logger->error($message);
        }

        return $res;
    }

    public function isVirtual(string $queue): bool
    {
        return $queue === QueueInterface::UPLOAD_QUEUE;
    }

    private function getRealTableName(): string
    {
        return $this->dbal->completeTableName(static::getTableName());
    }

    private function set(mixed $value, string $queue): void
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

        $result = $this->dbal->query($query);

        if (false === $result) {
            $message = vsprintf('Error while adding element to queue: %s', [
                $this->dbal->getLastErrorMessage(),
            ]);
            $this->logger->error($message);
            throw new SmartlingDbException($message);
        }
    }

    private function delete(mixed $id, string $queue): void
    {
        $id = (int)$id;

        $conditionBlock = ConditionBlock::getConditionBlock();

        $conditionBlock->addCondition(new Condition(ConditionBuilder::CONDITION_SIGN_EQ, 'queue', $queue));
        $conditionBlock->addCondition(new Condition(ConditionBuilder::CONDITION_SIGN_EQ, 'id', $id));

        $query = QueryBuilder::buildDeleteQuery($this->getRealTableName(), $conditionBlock, ['limit' => 1]);

        $result = $this->dbal->query($query);

        if (false === $result) {
            $message = vsprintf('Error while deleting element from queue: %s; Query: %s', [
                $this->dbal->getLastErrorMessage(),
                $query
            ]);
            $this->logger->error($message);
            throw new SmartlingDbException($message);
        }
    }

    private function get($queue): array
    {
        $conditionBlock = ConditionBlock::getConditionBlock();

        $conditionBlock->addCondition(new Condition(ConditionBuilder::CONDITION_SIGN_EQ, 'queue', $queue));

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

        $result = $this->dbal->fetch($query, \ARRAY_A);

        if (!is_array($result)) {
            $message = vsprintf('Error while getting element from queue: %s', [
                $this->dbal->getLastErrorMessage(),
            ]);
            $this->logger->error($message);
            throw new SmartlingDbException($message);
        }

        return $result;
    }

    public function enqueue(array $value, string $queue): void
    {
        if ($this->testValueToBeEnqueued($value)) {
            $encodedValue = json_encode($value, JSON_THROW_ON_ERROR);
            $this->set($encodedValue, $queue);
        }
    }

    private function extractValue(array $array, mixed $key): mixed
    {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        throw new \OutOfBoundsException(sprintf('Expected array %s to have key %s but it doesn\'t.',
            var_export($array, true),
            var_export($key, true),
        ));
    }

    public function dequeue(string $queue): mixed
    {
        $result = $this->get($queue);

        if (1 === count($result)) {
            $row = ArrayHelper::first($result);

            $id = $this->extractValue($row, 'id');
            $payload = $this->extractValue($row, 'payload');

            $value = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

            $this->delete($id, $queue);

            return $value;
        }

        return null;
    }

    /**
     * @throws SmartlingDbException
     */
    public function purge(?string $queue = null): void
    {
        $pageOptions = null;

        $condition = null;

        if (null !== $queue) {
            $condition = ConditionBlock::getConditionBlock();
            $condition->addCondition(new Condition(ConditionBuilder::CONDITION_SIGN_EQ, 'queue', $queue));
        }

        $query = QueryBuilder::buildDeleteQuery($this->getRealTableName(), $condition, $pageOptions);

        $result = $this->dbal->query($query);

        if (false === $result) {
            if (null !== $queue) {
                $template = 'Error while purging all elements from queue=%s. Message: %s';
                $message = vsprintf($template, [
                    $queue,
                    $this->dbal->getLastErrorMessage(),
                ]);
            } else {
                $template = 'Error while purging all elements from all queues. Message: %s';
                $message = vsprintf($template, [$this->dbal->getLastErrorMessage()]);
            }

            $this->logger->error($message);
            throw new SmartlingDbException($message);
        }
    }

    public function stats(): array
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

        $result = $this->dbal->fetch($query, \ARRAY_A);
        $output = [];
        foreach ($result as $row) {
            $output[$row['queue']] = (int)$row['num'];
        }

        return $output;
    }
}
