<?php

namespace Smartling\Extensions\AcfOptionPages;

use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\DbAl\WordpressContentEntities\Entity;
use Smartling\DbAl\WordpressContentEntities\VirtualEntityAbstract;
use Smartling\Helpers\QueryBuilder\Condition\Condition;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBlock;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBuilder;
use Smartling\Helpers\QueryBuilder\QueryBuilder;
use Smartling\WP\View\BulkSubmitScreenRow;

/**
 * @method string getName
 * @property int $id
 */
class AcfOptionEntity extends VirtualEntityAbstract
{
    private SmartlingToCMSDatabaseAccessWrapperInterface $dbal;

    /**
     * Standard 'option' content-type fields
     */
    protected array $fields = [
        'id',
        'name',
        'value',
    ];

    private array $map = [];

    public function __construct(SmartlingToCMSDatabaseAccessWrapperInterface $dbal)
    {
        parent::__construct();
        $this->dbal = $dbal;
        $this->type = ContentTypeAcfOption::WP_CONTENT_TYPE;
        $this->hashAffectingFields = array_merge($this->hashAffectingFields, ['name', 'value']);
        $this->setEntityFields($this->fields);
    }

    public function getTitle(): string
    {
        return $this->getName();
    }

    public function get(mixed $id): self
    {
        $this->buildMap();

        if (!array_key_exists($id, $this->map)) {
            $this->entityNotFound(ContentTypeAcfOption::WP_CONTENT_TYPE, $id);
        }

        return $this->resultToEntity($this->map[$id]->toArray());
    }

    private function getOptionNames(): array
    {
        $block = ConditionBlock::getConditionBlock();
        $condition = Condition::getCondition(
            ConditionBuilder::CONDITION_SIGN_LIKE,
            'option_name',
            ['options_%']
        );
        $block->addCondition($condition);
        $query = QueryBuilder::buildSelectQuery(
            $this->dbal->completeTableName('options'),
            ['option_name'],
            $block
        );

        return $this->dbal->fetch($query, \ARRAY_A);
    }

    /**
     * Reads all options and generates InMemory map to emulate get($guid)
     */
    private function buildMap(): void
    {
        $raw_options = $this->getOptionNames();
        $this->map = [];
        if (0 < count($raw_options)) {
            foreach ($raw_options as $raw_option) {
                $name = &$raw_option['option_name'];
                $optionEntity = AcfOptionHelper::getOption($name);
                $this->map[$optionEntity->getPk()] = $optionEntity;
            }
        }
    }

    /**
     * @return AcfOptionEntity[]
     */
    public function getAll(
        int $limit = 0,
        int $offset = 0,
        string $orderBy = '',
        string $order = '',
        string $searchString = '',
        array $ids = [],
    ): array {
        $this->buildMap();
        $collection = [];

        foreach ($this->map as $option) {
            $stateArray = $option->toArray();
            $collection[] = $this->resultToEntity($stateArray);
        }

        return array_slice($collection, $offset, $limit);
    }

    public function getTotal(): int
    {
        $this->buildMap();

        return (count($this->map));
    }

    /**
     * Stores entity to database
     */
    public function set(Entity $entity): int
    {
        if (!$entity instanceof self) {
            throw new \InvalidArgumentException(self::class . "->set() must be called with " . self::class);
        }
        $acfOptionHelper = AcfOptionHelper::fromArray($entity->toArray());
        $acfOptionHelper->write();

        return $acfOptionHelper->getPk();
    }

    public function getNonCloneableFields(): array
    {
        return [$this->getPrimaryFieldName()];
    }

    public function getPrimaryFieldName(): string
    {
        return 'id';
    }

    public function toArray(): array
    {
        $state = parent::toArray();
        unset($state['name']);

        return $state;
    }

    public function toBulkSubmitScreenRow(): BulkSubmitScreenRow
    {
        return new BulkSubmitScreenRow($this->getId(), $this->getTitle(), $this->getType());
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
