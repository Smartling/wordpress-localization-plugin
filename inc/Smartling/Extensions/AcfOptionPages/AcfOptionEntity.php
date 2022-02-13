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
 */
class AcfOptionEntity extends VirtualEntityAbstract
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
    public function setDbal($dbal)
    {
        $this->dbal = $dbal;
    }

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
    public function __construct()
    {
        parent::__construct();
        $this->setType(ContentTypeAcfOption::WP_CONTENT_TYPE);
        $this->hashAffectingFields = array_merge($this->hashAffectingFields, ['name', 'value']);
        $this->setEntityFields($this->fields);
    }

    protected function getFieldNameByMethodName($method)
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

    /**
     * @return array;
     */
    public function getMetadata()
    {
        return [];
    }

    /**
     * @return mixed
     */
    public function getTitle()
    {
        return $this->getName();
    }

    public function get($guid)
    {
        $this->buildMap();

        if (!array_key_exists($guid, $this->map)) {
            $this->entityNotFound(ContentTypeAcfOption::WP_CONTENT_TYPE, $guid);
        }

        return $this->resultToEntity($this->map[$guid]->toArray());
    }

    public function setMetaTag(string $tagName, $tagValue, bool $unique = true): void
    {
    }

    private function getOptionNames()
    {
        $block = ConditionBlock::getConditionBlock();
        $condition = Condition::getCondition(
            ConditionBuilder::CONDITION_SIGN_LIKE,
            'option_name',
            ['options_%']
        );
        $block->addCondition($condition);
        $query = QueryBuilder::buildSelectQuery(
            $this->getDbal()->completeTableName('options'),
            ['option_name'],
            $block
        );

        return $this->getDbal()->fetch($query, \ARRAY_A);
    }

    /**
     * Reads all options and generates InMemory map to emulate get($guid)
     */
    private function buildMap()
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
    public function getAll($limit = 0, $offset = 0, $orderBy = '', $order = '', $searchString = '')
    {
        $this->buildMap();
        $collection = [];

        foreach ($this->map as $option) {
            $stateArray = $option->toArray();
            $collection[] = $this->resultToEntity($stateArray);
        }

        return array_slice($collection, $offset, $limit);
    }

    /**
     * @return int
     */
    public function getTotal()
    {
        $this->buildMap();

        return (count($this->map));
    }

    /**
     * Stores entity to database
     *
     * @param EntityAbstract $entity
     *
     * @return mixed
     */
    public function set(EntityAbstract $entity = null)
    {
        if ($entity === null) {
            throw new \InvalidArgumentException(self::class . "->set() must be called with " . self::class);
        }
        $acfOptionHelper = AcfOptionHelper::fromArray($entity->toArray());
        $acfOptionHelper->write();

        return $acfOptionHelper->getPk();
    }

    /**
     * @return array
     */
    protected function getNonCloneableFields()
    {
        return [$this->getPrimaryFieldName()];
    }

    /**
     * @return string
     */
    public function getPrimaryFieldName()
    {
        return 'id';
    }

    public function toArray($skipNameField = true)
    {
        $state = parent::toArray();

        if (true === $skipNameField) {
            unset($state['name']);
        }

        return $state;
    }

    /**
     * Converts instance of EntityAbstract to array to be used for BulkSubmit screen
     * @return array
     */
    public function toBulkSubmitScreenRow()
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
}
