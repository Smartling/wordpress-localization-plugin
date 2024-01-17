<?php

namespace Smartling\DbAl\WordpressContentEntities;

use Smartling\Base\LoggerTrait;
use Smartling\Exception\EntityNotFoundException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Vendor\Psr\Log\LoggerInterface;
use Smartling\WP\View\BulkSubmitScreenRow;

abstract class EntityAbstract extends EntityBase implements EntityHandler
{
    use LoggerTrait;

    protected string $type = 'abstract';

    /**
     * List of fields that affect the hash of the entity
     */
    protected array $hashAffectingFields = [];

    private array $entityFields = ['hash'];

    private LoggerInterface $logger;

    /**
     * @var array List of related content-types to search
     */
    private array $relatedTypes = [];

    public function getRelatedTypes(): array
    {
        return $this->relatedTypes;
    }

    public function setRelatedTypes(array $relatedTypes): void
    {
        $this->relatedTypes = $relatedTypes;
    }

    private array $entityArrayState = [];

    private function initEntityArrayState(): void
    {
        if (0 === count($this->entityArrayState)) {
            foreach ($this->entityFields as $field) {
                $this->entityArrayState[$field] = null;
            }
        }
    }

    public function fromArray(array $array): static
    {
        $result = clone $this;
        $result->entityArrayState = array_merge(['hash'], $array);
        $result->entityFields = array_merge(['hash'], $array);
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

    public function __isset(string $name): bool
    {
        return array_key_exists($name, $this->entityArrayState);
    }

    protected function getFieldNameByMethodName(string $method): string
    {
        return strtolower(preg_replace('/([A-Z])/', '_$1', lcfirst(substr($method, 3))));
    }

    /**
     * @return mixed|void
     */
    public function __call(string $method, array $params)
    {
        switch (substr($method, 0, 3)) {
            case 'set':
                $field = $this->getFieldNameByMethodName($method);
                $this->$field = ArrayHelper::first($params); // get the very first arg
                break;
            case 'get':
                $field = $this->getFieldNameByMethodName($method);

                return $this->$field; // get the very first arg
            default:
                throw new \BadMethodCallException(sprintf("Method $method does not exists in class '%s'", get_class($this)));
        }
    }

    abstract public function getTitle(): string;

    abstract public function getContentTypeProperty(): string;

    protected function validateContentType(): bool
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

    protected function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    public function __construct()
    {
        $this->logger = MonologWrapper::getLogger(get_called_class());
    }

    /**
     * Loads the entity from database
     * @throws EntityNotFoundException
     */
    abstract public function get(mixed $id): self;

    /**
     * @return static[]
     */
    // FIXME TODO : Method must be static or better moved out from this class
    // It's not a good idea to ask instance of Post\Tag class to return all objects
    abstract public function getAll(
        int $limit = 0,
        int $offset = 0,
        string $orderBy = '',
        string $order = '',
        string $searchString = '',
        array $includeOnlyIds = [],
    ): array;

    /**
     * @return int
     */
    // FIXME TODO : Method must be static or better moved out from this class
    // It's not good idea to ask instence of Post\Tag class to return all objects
    abstract public function getTotal(): int;

    /**
     * Stores entity to database
     */
    abstract public function set(Entity $entity): int;

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
    abstract protected function getNonCloneableFields(): array;

    public function forInsert(): static
    {
        $result = clone $this;
        foreach ($result->getNonCloneableFields() as $field) {
            $result->$field = null;
        }
        return $result;
    }

    abstract public function getPrimaryFieldName(): string;

    public function getPK(): int
    {
        return (int)$this->{$this->getPrimaryFieldName()};
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType($type): void
    {
        $this->type = $type;
    }

    /**
     * Converts instance of EntityAbstract to array to be used for BulkSubmit screen
     */
    abstract public function toBulkSubmitScreenRow(): BulkSubmitScreenRow;

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
