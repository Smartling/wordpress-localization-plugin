<?php

namespace Smartling\DbAl\WordpressContentEntities;

use Smartling\Base\LoggerTrait;
use Smartling\Exception\EntityNotFoundException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Vendor\Psr\Log\LoggerInterface;

abstract class EntityAbstract implements EntityInterface
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

    public function fromArray(array $array): self
    {
        $result = clone $this;
        $result->entityFields = array_merge(['hash'], $array);
        $result->initEntityArrayState();
        return $result;
    }

    public function setEntityFields(array $entityFields): void
    {
        $this->entityFields = array_merge(['hash'], $entityFields);
        $this->initEntityArrayState();
    }

    public function toArray(): array
    {
        return $this->entityArrayState;
    }

    /**
     * property-like magic getter for entity fields
     *
     * @param $fieldName
     *
     * @return mixed|void
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
     * @return mixed|void
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
            default :
                $template = 'Method \'%s\' does not exists in class \'%s\'';
                $message = vsprintf($template, [$method, get_class($this)]);
                throw new \BadMethodCallException($message);
        }
    }

    abstract public function getTitle(): string;

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
        }

        if ($this->{$this->getContentTypeProperty()} !== $this->getType()) {
            $message = vsprintf('Requested content with invalid content-type, expected \'%s\', got \'%s\'.', [
                $this->{$this->getContentTypeProperty()},
                $this->getType(),
            ]);
            $this->getLogger()->warning($message);

            return false;
        }

        return true;
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
     * @param mixed $guid
     * @return EntityAbstract
     * @throws EntityNotFoundException
     */
    abstract public function get($guid);

    /**
     * @param string $tagName
     * @param string $tagValue
     * @param bool   $unique
     */
    abstract public function setMetaTag($tagName, $tagValue, $unique = true): void;

    /**
     * @param int $limit
     * @param int $offset
     * @param string $orderBy
     * @param string $order
     * @param string $searchString
     * @return static[]
     */
    // FIXME TODO : Method must be static or better moved out from this class
    // It's not good idea to ask instence of Post\Tag class to return all objects
    abstract public function getAll($limit = 0, $offset = 0, $orderBy = '', $order = '', $searchString = '');

    /**
     * @return int
     */
    // FIXME TODO : Method must be static or better moved out from this class
    // It's not good idea to ask instence of Post\Tag class to return all objects
    abstract public function getTotal();

    /**
     * Stores entity to database
     * @param EntityAbstract $entity
     * @return int
     */
    abstract public function set(EntityAbstract $entity = null);

    protected function resultToEntity(array $arr): static
    {
        $className = get_class($this);
        $entity = new $className($this->getType(), $this->getRelatedTypes());

        foreach ($this->fields as $fieldName) {
            if (array_key_exists($fieldName, $arr)) {
                $entity->$fieldName = $arr[$fieldName];
            }
        }
        if (property_exists($entity, 'hash')) {
            $entity->hash = '';
        }

        return $entity;
    }

    protected function entityNotFound(string $type, $guid): void
    {
        $template = 'Entity not found in the database, localizedContentType="%s", contentType="%s", contentId="%s", className="%s", currentBlogId=%d';
        $message = sprintf($template, WordpressContentTypeHelper::getLocalizedContentType($type), $type, $guid, static::class, get_current_blog_id());
        throw new EntityNotFoundException($message);
    }

    /**
     * @return string[]
     */
    abstract protected function getNonCloneableFields();

    /**
     * @param mixed $value
     */
    public function cleanFields($value = null)
    {
        foreach ($this->getNonCloneableFields() as $field) {
            $this->$field = $value;
        }
    }

    /**
     * @return string
     */
    abstract public function getPrimaryFieldName();

    /**
     * @return int
     */
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
    abstract public function toBulkSubmitScreenRow(): array;

    protected function areMetadataValuesUnique(array $metadata): bool
    {
        $valueHash = static function ($value) {
            /** @noinspection JsonEncodingApiUsageInspection */
            return md5(json_encode($value));
        };

        if (1 < count($metadata)) {
            $firstHash = $valueHash(array_shift($metadata));
            foreach ($metadata as $metadatum) {
                if ($valueHash($metadatum) !== $firstHash) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function formatMetadata(array $metadata): array
    {
        foreach ($metadata as &$mValue) {
            if (!$this->areMetadataValuesUnique($mValue)) {
                $mValue = ArrayHelper::first($mValue);
            } else {
                /** @noinspection JsonEncodingApiUsageInspection */
                $this->getLogger()->warning(sprintf(
                    "Detected unsupported metadata: '%s' for entity %s='%s'",
                    \json_encode($metadata),
                    $this->getPrimaryFieldName(),
                    $this->getPK(),
                ));

                $lastValue = ArrayHelper::last($mValue);

                /** @noinspection JsonEncodingApiUsageInspection */
                $this->getLogger()->warning(sprintf(
                    "Got unsupported metadata '%s' for post ID='%s' Continue using last value = '%s'.",
                    \json_encode($mValue),
                    $this->getPK(),
                    $lastValue,
                ));

                $mValue = $lastValue;
            }
        }

        return $metadata;
    }
}
