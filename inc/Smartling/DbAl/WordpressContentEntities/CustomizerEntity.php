<?php

namespace Smartling\DbAl\WordpressContentEntities;

use Smartling\Exception\EntityNotFoundException;

class CustomizerEntity extends VirtualEntityAbstract
{
    /**
     * the fields are read and changed by EntityAbstract, so protected
     * @see \Smartling\DbAl\WordpressContentEntities\EntityAbstract::resultToEntity
     */
    protected array $fields = ['id', 'title'];
    protected ?string $id = null;
    protected ?string $title = null;

    public function __construct($type)
    {
        parent::__construct();

        $this->hashAffectingFields = array_merge($this->hashAffectingFields, $this->fields);

        $this->setEntityFields($this->fields);
        $this->setType($type);
        $this->setRelatedTypes([]);
    }

    public function getTitle(): string
    {
        return (string)$this->title;
    }

    /**
     * @param string $guid
     * @throws EntityNotFoundException
     */
    public function get($guid): void
    {
        throw new EntityNotFoundException('Unable to find customizer entity'); // TODO
    }

    private function getList(): array
    {
        $result = [];
        global $wp_customize;
        foreach ($wp_customize->controls() as $control) {
            // we only want the WP_Customize_Control, not it's descendants
            if (get_class($control) !== \WP_Customize_Control::class) {
                continue;
            }
            $result[] = ['id' => $control->id, 'title' => $control->value()];
        }
        return $result;
    }

    /**
     * @param int $limit
     * @param int $offset
     * @param string $orderBy
     * @param string $order
     * @param string $searchString
     * @return self[]
     */
    public function getAll($limit = 0, $offset = 0, $orderBy = '', $order = '', $searchString = ''): array
    {
        $collection = [];
        foreach ($this->getList() as $item) {
            $collection[] = $this->resultToEntity($item);
        }

        return array_slice($collection, $offset, $limit);
    }


    public function getTotal(): int
    {
        return count($this->getList());
    }

    public function set(EntityAbstract $entity = null): void
    {
        // TODO implement target blog saving
    }

    public function toBulkSubmitScreenRow(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type'    => $this->getType(),
            'author'  => null,
            'status'  => null,
            'locales' => null,
            'updated' => null,
        ];
    }
}
