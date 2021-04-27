<?php

namespace Smartling\DbAl\WordpressContentEntities;

use JetBrains\PhpStorm\ArrayShape;
use Smartling\Exception\EntityNotFoundException;

class CustomizerEntity extends VirtualEntityAbstract implements PropertySettableInterface
{
    /**
     * the fields are read and changed by EntityAbstract, so protected
     * @see \Smartling\DbAl\WordpressContentEntities\EntityAbstract::resultToEntity
     */
    protected array $fields = ['contents', 'title'];
    protected array $contents = [];
    protected int $id = 0;
    protected string $title = '';

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
        return $this->title;
    }

    /**
     * @param int $guid
     * @throws EntityNotFoundException
     */
    public function get($guid): self
    {
        global $wp_customize;

        if (!$wp_customize instanceof \WP_Customize_Manager) {
            $this->getLogger()->debug('Tried to upload customizer entity, but $wp_customize is ' .
                is_scalar($wp_customize) ? gettype($wp_customize) : get_class($wp_customize));
            throw new EntityNotFoundException('Unable to find customizer entity');
        }

        return $this->resultToEntity(['contents' => $this->getControls($wp_customize), 'title' => get_template()]);
    }

    private function getList(): array
    {
        global $wp_customize;
        $count = count($this->getControls($wp_customize));

        return [['id' => 0, 'title' => get_template() . " customization ($count settings)"]];
    }

    private function getControls(\WP_Customize_Manager $manager): array
    {
        $result = [];
        foreach ($manager->controls() as $control) {
            if (get_class($control) !== \WP_Customize_Control::class // only plain text controls
                || (string)$control->value() === ''
                || preg_match('/^\d+$/', $control->value())
            ) {
                continue;
            }
            $result[] = $this->buildItem($control->id, $control->value());
        }
        return $result;
    }

    #[ArrayShape(['id' => 'string', 'value' => 'string'])]
    private function buildItem(string $id, string $value): array
    {
        return ['id' => $id, 'value' => $value];
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

    public function set(EntityAbstract $entity = null): int
    {
        if (!$entity instanceof self) {
            throw new \RuntimeException(self::class . ' expected');
        }
        $key = "theme_mods_{$entity->title}";
        $option = get_option($key);
        if (!is_array($option)) {
            $option = [];
        }
        foreach ($entity->contents as $control) {
            $option[$control['id']] = $control['value'];
        }

        update_option($key, $option);

        return crc32($entity->title);
    }

    public function setProperty($name, $value): void
    {
        $item = $this->buildItem($name, $value);
        $id = array_search($name, array_column($this->contents, 'id'), true);
        if ($id !== false) {
            $this->contents[$id] = $item;
        } else {
            $this->contents[] = $item;
        }
    }

    public function toBulkSubmitScreenRow(): array
    {
        return [
            'id' => 0,
            'title' => $this->title,
            'type' => $this->getType(),
            'author' => null,
            'status' => null,
            'locales' => null,
            'updated' => null,
        ];
    }

    protected function getNonCloneableFields(): array
    {
        return [];
    }

    public function toArray(): array
    {
        $result = [];
        foreach ($this->contents as $control) {
            $result[$control['id']] = $control['value'];
        }
        return $result;
    }
}
