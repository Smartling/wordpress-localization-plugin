<?php

namespace Smartling\DbAl\WordpressContentEntities;

use JetBrains\PhpStorm\ArrayShape;
use Smartling\DbAl\DB;
use Smartling\Exception\EntityNotFoundException;
use Smartling\Exception\SmartlingDbException;
use Smartling\Helpers\QueryBuilder\Condition\Condition;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBlock;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBuilder;

class GravityFormsFormHandler implements EntityHandler {
    private DB $db;
    private string $tableName;
    private string $tableMetaName;

    public function __construct(DB $db)
    {
        $this->db = $db;
        $this->tableName = "{$this->db->getPrefix()}gf_form";
        $this->tableMetaName = "{$this->db->getPrefix()}gf_form_meta";
    }

    public function get(int $id): GravityFormsForm
    {
        $data = $this->getFormData($id);
        return new GravityFormsForm($this->getMeta($id)['display_meta'], $id, $data['title'], $data['date_updated']);
    }

    public function getAll(int $limit = 50, int $offset = 0, string $orderBy = '', string $order = '', string $searchString = ''): array
    {
        $condition = new ConditionBlock(ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_AND);
        if ($searchString !== '') {
            $condition->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, 'title', [$searchString]));
        }
        $query = "select id, title, date_updated from $this->tableName";
        if (count($condition->getConditions()) > 0) {
            $query .= " where $condition";
        }
        $query .= " limit $offset, $limit";

        return array_map(static function (array $item) {
            return new GravityFormsForm('', $item['id'], $item['title'], $item['date_updated']);
        }, $this->db->fetch($query, ARRAY_A));
    }

    #[ArrayShape([
        'title' => 'string',
        'date_updated' => 'string',
    ])]
    public function getFormData(int $id): array
    {
        $result = $this->db->fetchPrepared("select title, date_updated from $this->tableName where id = %s", $id);
        if (count($result) !== 1) {
            throw new EntityNotFoundException("Unable to get form data for Gravity Form $id");
        }

        return $result[0];
    }

    #[ArrayShape([
        'display_meta' => 'string',
    ])]
    public function getMeta(int $id): array
    {
        $result = $this->db->fetchPrepared("select display_meta from $this->tableMetaName where form_id = %s", $id);
        if (count($result) !== 1) {
            throw new EntityNotFoundException("Unable to get meta for Gravity Form $id");
        }

        return $result[0];
    }

    public function getTitle(int $id): string
    {
        return $this->getFormData($id)['title'];
    }

    public function getTotal(): int
    {
        return (int)$this->db->fetch("select count(*) cnt from $this->tableName", ARRAY_A)[0]['cnt'];
    }

    public function set(Entity $entity): int
    {
        if (!$entity instanceof GravityFormsForm) {
            throw new \InvalidArgumentException("Handler only supports setting " . GravityFormsForm::class . ", " . get_class($entity) . " provided");
        }
        return $entity->getId() === null ? $this->insert($entity) : $this->update($entity);
    }

    private function insert(GravityFormsForm $entity): int
    {
        $id = $this->db->queryPrepared("insert into $this->tableName (title) values ('%s')", $entity->getTitle());
        if (!is_int($id)) {
            throw new SmartlingDbException("Unable to insert entity into gf_form table");
        }
        if ($this->db->queryPrepared("insert into $this->tableMetaName (display_meta, form_id) values (%s, %s)", $entity->getDisplayMeta(), $id) !== 1) {
            $this->db->queryPrepared("delete from $this->tableName where id = %s", $id);
            throw new SmartlingDbException("Failed to insert form meta for id {$entity->getId()}");
        }
        return $id;
    }

    private function update(GravityFormsForm $entity): int
    {
        if ($this->db->fetchPrepared("select count(*) cnt from $this->tableName where id = %s", $entity->getId())[0]['cnt'] !== "1" ||
            $this->db->fetchPrepared("select count(*) cnt from $this->tableMetaName where form_id = %s", $entity->getId())[0]['cnt'] !== "1") {
            throw new SmartlingDbException("Failed to update form table by id {$entity->getId()}");
        }
        $this->db->queryPrepared("update {$this->db->getPrefix()}gf_form set title = '%s' where id = %s", $entity->getTitle(), $entity->getId());
        $this->db->queryPrepared("update {$this->db->getPrefix()}gf_form_meta set display_meta = %s where form_id = %s", $entity->getDisplayMeta(), $entity->getId());
        return $entity->getId();
    }
}
