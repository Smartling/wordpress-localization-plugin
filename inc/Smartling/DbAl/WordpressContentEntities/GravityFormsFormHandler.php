<?php

namespace Smartling\DbAl\WordpressContentEntities;

use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\Exception\EntityNotFoundException;
use Smartling\Exception\SmartlingDbException;
use Smartling\Helpers\QueryBuilder\Condition\Condition;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBlock;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBuilder;

class GravityFormsFormHandler implements EntityHandler {
    private SmartlingToCMSDatabaseAccessWrapperInterface $db;

    public function __construct(SmartlingToCMSDatabaseAccessWrapperInterface $db)
    {
        $this->db = $db;
    }

    public function get(mixed $id): GravityFormsForm
    {
        $data = $this->getFormData($id);
        return new GravityFormsForm($data->getDisplayMeta(), $id, $data->getTitle(), $data->getUpdated());
    }

    public function getAll(
        int $limit = 50,
        int $offset = 0,
        string $orderBy = '',
        string $order = '',
        string $searchString = '',
        array $ids = [],
    ): array {
        $where = new ConditionBlock(ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_AND);
        if ($searchString !== '') {
            $where->addCondition(new Condition(ConditionBuilder::CONDITION_SIGN_EQ, 'title', $searchString));
        }
        if (count($ids) > 0) {
            $where->addCondition(new Condition(ConditionBuilder::CONDITION_SIGN_IN, 'id', $ids));
        }
        $query = "select id, title, date_updated from {$this->getTableName()}";
        if (count($where->getConditions()) > 0) {
            $query .= " where $where";
        }
        $query .= " limit $offset, $limit";

        return array_map(static function (array $item) {
            return new GravityFormsForm('', $item['id'], $item['title'], $item['date_updated']);
        }, $this->db->fetch($query, ARRAY_A));
    }

    public function getFormData(int $id): GravityFormFormData
    {
        $form = $this->db->fetchPrepared("select title, date_updated from {$this->getTableName()} where id = %s", $id);
        if (count($form) !== 1) {
            throw new EntityNotFoundException("Unable to get form data for Gravity Form $id");
        }
        $form = $form[0];
        $meta = $this->db->fetchPrepared("select display_meta from {$this->getTableMetaName()} where form_id = %s", $id);
        if (count($meta) !== 1) {
            throw new EntityNotFoundException("Unable to get meta for Gravity Form $id");
        }
        $meta = $meta[0];

        return new GravityFormFormData($meta['display_meta'], $form['title'], $form['date_updated']);
    }

    private function getTableMetaName(): string
    {
        return "{$this->db->getPrefix()}gf_form_meta";
    }

    private function getTableName(): string
    {
        return "{$this->db->getPrefix()}gf_form";
    }

    public function getTotal(): int
    {
        return (int)$this->db->fetch("select count(*) cnt from {$this->getTableName()}", ARRAY_A)[0]['cnt'];
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
        $id = $this->db->queryPrepared("insert into {$this->getTableName()} (title) values ('%s')", $entity->getTitle());
        if (!is_int($id)) {
            throw new SmartlingDbException("Unable to insert entity into form table");
        }
        if ($this->db->queryPrepared("insert into {$this->getTableMetaName()} (display_meta, form_id) values (%s, %s)", $entity->getDisplayMeta(), $id) !== 1) {
            $this->db->queryPrepared("delete from {$this->getTableName()} where id = %s", $id);
            throw new SmartlingDbException("Failed to insert form meta for id {$entity->getId()}");
        }
        return $id;
    }

    private function update(GravityFormsForm $entity): int
    {
        if ($this->db->fetchPrepared("select count(*) cnt from {$this->getTableName()} where id = %s", $entity->getId())[0]['cnt'] !== "1" ||
            $this->db->fetchPrepared("select count(*) cnt from {$this->getTableMetaName()} where form_id = %s", $entity->getId())[0]['cnt'] !== "1") {
            throw new SmartlingDbException("Failed to update form table by id {$entity->getId()}");
        }
        $this->db->queryPrepared("update {$this->getTableName()} set title = '%s' where id = %s", $entity->getTitle(), $entity->getId());
        $this->db->queryPrepared("update {$this->getTableMetaName()} set display_meta = %s where form_id = %s", $entity->getDisplayMeta(), $entity->getId());
        return $entity->getId();
    }
}
