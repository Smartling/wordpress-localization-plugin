<?php

namespace Smartling\DbAl\WordpressContentEntities;

use Psr\Log\LoggerInterface;
use Smartling\Base\LoggerTrait;
use Smartling\Exception\EntityNotFoundException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\MonologWrapper\MonologWrapper;

/**
 * Class EntityAbstract
 * @package Smartling\DbAl\WordpressContentEntities
 */
abstract class EntityAbstract
{

    use LoggerTrait;

    /**
     * @var string
     */
    protected $type = 'abstract';

    /**
     * List of fields that affect the hash of the entity
     * @var array
     */
    protected $hashAffectingFields = [];

    /**
     * @var array
     */
    private $entityFields = ['hash'];

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var array List of related content-types to search
     */
    private $relatedTypes = [];

    /**
     * @return array
     */
    public function getRelatedTypes()
    {
        return $this->relatedTypes;
    }

    /**
     * @param array $relatedTypes
     */
    public function setRelatedTypes($relatedTypes)
    {
        $this->relatedTypes = $relatedTypes;
    }

    public function resetRelatedTypes()
    {
        $this->relatedTypes = [];
    }

    /**
     * @param string $relatedType
     */
    public function addRelatedType($relatedType)
    {
        $this->relatedTypes[] = $relatedType;
    }


    private $entityArrayState = [];

    private function initEntityArrayState()
    {
        if (0 === count($this->entityArrayState)) {
            foreach ($this->entityFields as $field) {
                $this->entityArrayState[$field] = null;
            }
        }
    }

    /**
     * @param array $entityFields
     */
    public function setEntityFields(array $entityFields)
    {
        $this->entityFields = array_merge(['hash'], $entityFields);
        $this->initEntityArrayState();
    }

    /**
     * Transforms entity instance into array
     * @return array
     */
    public function toArray()
    {
        return $this->entityArrayState;
    }

    /**
     * property-like magic getter for entity fields
     *
     * @param $fieldName
     *
     * @return mixed
     */
    public function __get($fieldName)
    {
        if (isset($this->{$fieldName})) {
            return $this->entityArrayState[$fieldName];
        }
    }

    /**
     * property-like magic setter for entity fields
     *
     * @param $fieldName
     * @param $fieldValue
     */
    public function __set($fieldName, $fieldValue)
    {
        if (isset($this->{$fieldName})) {
            $this->entityArrayState[$fieldName] = $fieldValue;
        }
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function __isset($name)
    {
        return array_key_exists($name, $this->entityArrayState);
    }

    /**
     * @param string $method
     *
     * @return string
     */
    protected function getFieldNameByMethodName($method)
    {
        return strtolower(preg_replace('/([A-Z])/', '_$1', lcfirst(substr($method, 3))));
    }

    /**
     * @param string $method
     * @param array  $params
     *
     * @return mixed
     */
    public function __call($method, array $params)
    {
        switch (substr($method, 0, 3)) {
            case 'set' :
                $field = $this->getFieldNameByMethodName($method);
                $this->$field = ArrayHelper::first($params); // get the very first arg
                break;
            case 'get' :
                $field = $this->getFieldNameByMethodName($method);

                return $this->$field; // get the very first arg
                break;
            default :
                $template = 'Method \'%s\' does not exists in class \'%s\'';
                $message = vsprintf($template, [$method, get_class($this)]);
                throw new \BadMethodCallException($message);
                break;
        }
    }

    /**
     * @return array;
     */
    abstract public function getMetadata();

    /**
     * @return mixed
     */
    abstract public function getTitle();

    /**
     * @return string
     */
    abstract public function getContentTypeProperty();

    /**
     * @return bool
     */
    protected function validateContentType()
    {
        if ($this instanceof VirtualEntityAbstract) {
            return true;
        } else {
            if ($this->{$this->getContentTypeProperty()} !== $this->getType()) {
                $message = vsprintf('Requested content with invalid content-type, expected \'%s\', got \'%s\'.', [
                    $this->{$this->getContentTypeProperty()},
                    $this->getType(),
                ]);
                $this->getLogger()->debug($message);

                return false;
            } else {
                return true;
            }
        }
    }


    /**
     * @return LoggerInterface
     */
    protected function getLogger()
    {
        return $this->logger;
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->logger = MonologWrapper::getLogger(get_called_class());
    }

    /**
     * Loads the entity from database
     *
     * @param $guid
     *
     * @return EntityAbstract
     */
    abstract public function get($guid);

    /**
     * @param string $tagName
     * @param string $tagValue
     * @param bool   $unique
     */
    abstract public function setMetaTag($tagName, $tagValue, $unique = true);

    /**
     * @param string $limit
     * @param int    $offset
     * @param bool   $orderBy
     * @param bool   $order
     *
     * @return mixed
     */
    // FIXME TODO : Method must be static or better moved out from this class
    // It's not good idea to ask instence of Post\Tag class to return all objects
    abstract public function getAll($limit = '', $offset = 0, $orderBy = false, $order = false, $searchString = '');

    /**
     * @return int
     */
    // FIXME TODO : Method must be static or better moved out from this class
    // It's not good idea to ask instence of Post\Tag class to return all objects
    abstract public function getTotal();

    /**
     * Stores entity to database
     *
     * @param EntityAbstract $entity
     *
     * @return mixed
     */
    abstract public function set(EntityAbstract $entity = null);

    /**
     * Converts object into EntityAbstract child
     *
     * @param array          $arr
     * @param EntityAbstract $entity
     *
     * @return EntityAbstract
     */
    protected function resultToEntity(array $arr)
    {
        $className = get_class($this);
        $entity = new $className($this->getType(), $this->getRelatedTypes());

        foreach ($this->fields as $fieldName) {
            if (array_key_exists($fieldName, $arr)) {
                $entity->$fieldName = $arr[$fieldName];
            }
        }
        $entity->hash = '';

        return $entity;
    }

    protected function entityNotFound($type, $guid)
    {
        $template = 'The \'%s\' with ID %s not found in the database.';
        $message = vsprintf($template, [WordpressContentTypeHelper::getLocalizedContentType($type), $guid]);
        throw new EntityNotFoundException($message);
    }

    /**
     * @return array
     */
    protected abstract function getNonClonableFields();

    /**
     * @return EntityAbstract
     */
    public function __clone()
    {
        $nonCloneFields = $this->getNonClonableFields();

        $myFields = $this->toArray();
        if (is_array($nonCloneFields) && 0 < count($nonCloneFields)) {
            foreach ($nonCloneFields as $field) {
                unset ($myFields[$field]);
            }
        }
        $this->resultToEntity($myFields);
    }

    /**
     * @param null $value
     */
    public function cleanFields($value = null)
    {
        foreach ($this->getNonClonableFields() as $field) {
            $this->$field = $value;
        }

    }

    /**
     * Is called when downloaded with 100% translation
     */
    public function translationCompleted()
    {
    }

    /**
     * Is called when cloned source content
     */
    public function translationDrafted()
    {
    }

    /**
     * @return string
     */
    public abstract function getPrimaryFieldName();

    public function getPK()
    {
        return (int)$this->{$this->getPrimaryFieldName()};
    }


    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * Converts instance of EntityAbstract to array to be used for BulkSubmit screen
     * @return array
     */
    abstract public function toBulkSubmitScreenRow();
}
