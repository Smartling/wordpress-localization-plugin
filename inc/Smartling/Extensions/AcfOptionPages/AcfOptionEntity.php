<?php

namespace Smartling\Extensions\AcfOptionPages;

use Psr\Log\LoggerInterface;
use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\DbAl\WordpressContentEntities\EntityAbstract;
use Smartling\DbAl\WordpressContentEntities\VirtualEntityAbstract;
use Smartling\Helpers\QueryBuilder\Condition\Condition;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBlock;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBuilder;
use Smartling\Helpers\QueryBuilder\QueryBuilder;

/**
 * Class AcfOptionEntity
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
    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);
        $this->setType(ContentTypeAcfOption::WP_CONTENT_TYPE);
        $this->hashAffectingFields = array_merge($this->hashAffectingFields, ['name', 'value']);
        $this->setEntityFields($this->fields);
    }

    protected function getFieldNameByMethodName($method)
    {
        $way = substr($method, 0, 3);
        $possibleField = lcfirst(substr($method, 3));
        if (in_array($way, ['set', 'get']) && in_array($possibleField, $this->fields)) {
            return $possibleField;
        } else {
            $message = vsprintf('Method %s not found in %s', [$method, __CLASS__]);
            $this->getLogger()->error($message);
            throw new \BadMethodCallException($message);
        }
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

    /**
     * @param string $tagName
     * @param string $tagValue
     * @param bool   $unique
     */
    public function setMetaTag($tagName, $tagValue, $unique = true)
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
     * @param string $limit
     * @param int    $offset
     * @param bool   $orderBy
     * @param bool   $order
     *
     * @return mixed
     */
    public function getAll($limit = '', $offset = 0, $orderBy = false, $order = false, $searchString = '')
    {
        $this->buildMap();
        $collection = [];

        foreach ($this->map as $option) {
            $stateArray = $option->toArray();
            $collection[] = $this->resultToEntity($stateArray);
        }

        return self::paginateArray($collection, $limit, $offset);
    }

    /**
     * @param array $data
     * @param int   $limit
     * @param int   $offset
     *
     * @return array
     */
    private static function paginateArray(array $data, $limit, $offset)
    {
        // apply offset;
        for ($i = 0; $i < $offset; $i++) {
            if (0 < count($data)) {
                array_shift($data);
            }
        }

        // get page
        $result = [];
        for ($i = 0; $i < $limit; $i++) {
            if (0 < count($data)) {
                $result[] = array_shift($data);
            }
        }

        return $result;
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
        /** @var AcfOptionHelper $entity */
        $acfOptionHelper = AcfOptionHelper::fromArray($entity->toArray(false));
        $acfOptionHelper->write();

        return $acfOptionHelper->getPk();
    }

    /**
     * @return array
     */
    protected function getNonClonableFields()
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
