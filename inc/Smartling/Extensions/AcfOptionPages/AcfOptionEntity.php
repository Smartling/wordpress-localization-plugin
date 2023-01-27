<?php

namespace Smartling\Extensions\AcfOptionPages;

use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\DbAl\WordpressContentEntities\EntityAbstract;
use Smartling\DbAl\WordpressContentEntities\VirtualEntityAbstract;
use Smartling\Helpers\QueryBuilder\Condition\Condition;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBlock;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBuilder;
use Smartling\Helpers\QueryBuilder\QueryBuilder;

/**
 * @method string getName
 * @property int $id
 */
class AcfOptionEntity extends VirtualEntityAbstract
{
    private SmartlingToCMSDatabaseAccessWrapperInterface $dbal;

    /**
     * Standard 'option' content-type fields
     * @var array
     */
    protected $fields = [
        'id',
        'name',
        'value',
    ];

    private $map = [];

    /**
     * @inheritdoc
     */
    public function __construct(SmartlingToCMSDatabaseAccessWrapperInterface $dbal)
    {
        parent::__construct();
        $this->dbal = $dbal;
        $this->type = ContentTypeAcfOption::WP_CONTENT_TYPE;
        $this->hashAffectingFields = array_merge($this->hashAffectingFields, ['name', 'value']);
        $this->setEntityFields($this->fields);
    }

    protected function getFieldNameByMethodName($method): string
    {
        $way = substr($method, 0, 3);
        $possibleField = lcfirst(substr($method, 3));
        if (in_array($way, ['set', 'get']) && in_array($possibleField, $this->fields, true)) {
            return $possibleField;
        }

        $message = vsprintf('Method %s not found in %s', [$method, __CLASS__]);
        $this->getLogger()->error($message);
        throw new \BadMethodCallException($message);
    }

    public function getTitle(): string
    {
        return $this->getName();
    }

    public function get($guid): self
    {
        $this->buildMap();

        if (!array_key_exists($guid, $this->map)) {
            $this->entityNotFound(ContentTypeAcfOption::WP_CONTENT_TYPE, $guid);
        }

        return $this->resultToEntity($this->map[$guid]->toArray());
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
     * @param int $limit
     * @param int $offset
     * @param string $orderBy
     * @param string $order
     * @param string $searchString
     * @return AcfOptionEntity[]
     */
    public function getAll($limit = 0, $offset = 0, $orderBy = '', $order = '', $searchString = ''): array
    {
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
    public function set(EntityAbstract $entity = null): ?int
    {
        if ($entity === null) {
            throw new \InvalidArgumentException(self::class . "->set() must be called with " . self::class);
        }
        $acfOptionHelper = AcfOptionHelper::fromArray($entity->toArray());
        $acfOptionHelper->write();

        return $acfOptionHelper->getPk();
    }

    protected function getNonCloneableFields(): array
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

    public function toBulkSubmitScreenRow(): array
    {
        return [
            'id'      => $this->getId(),
            'title'   => $this->getTitle(),
            'type'    => $this->getType(),
            'author'  => null,
            'status'  => null,
            'locales' => null,
            'updated' => null,
        ];

    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
