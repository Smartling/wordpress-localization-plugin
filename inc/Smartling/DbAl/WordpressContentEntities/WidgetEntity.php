<?php

namespace Smartling\DbAl\WordpressContentEntities;

use Smartling\Helpers\StringHelper;
use Smartling\Helpers\ThemeSidebarHelper;
use Smartling\Helpers\WidgetHelper;
use Smartling\WP\View\BulkSubmitScreenRow;

/**
 * @property int    $id                 Unique id
 * @property string $widgetType         widget type
 * @property int    $index              Widget descriptor index (Wordpress internal)
 * @property string $bar                Sidebar Id
 * @property int    $barPosition        Widget Position in Sidebar
 * @property array  $settings           Widget settings
 * @method array    getSettings()       Returns settings key => value array
 * @method string   getWidgetType()     Returns Wordpress Widget type
 * @method int      getIndex()          Returns Widget index
 * @method string   getBar()            Returns bar related to index
 * @method int      getBarPosition()    Returns Widget position in the bar
 * @method array setSettings(array $settings)
 */
class WidgetEntity extends VirtualEntityAbstract
{
    /**
     * @var WidgetHelper[] All widgets of current theme.
     */
    protected array $map = [];

    protected array $fields = [
        'id',
        'widgetType',
        'index',
        'bar',
        'barPosition',
        'settings',
    ];

    public function __construct($type, array $related = [])
    {
        parent::__construct();

        $this->hashAffectingFields = array_merge($this->hashAffectingFields, [
            'id',
            'widgetType',
            'index',
            'bar',
            'barPosition',
        ]);

        $this->setEntityFields($this->fields);
        $this->setType($type);
        $this->setRelatedTypes($related);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $result = clone $this;
        $this->id = $id;

        return $result;
    }

    public function getTitle(): string
    {
        $title = $this->getSettings()['title'] ?? null;

        return StringHelper::isNullOrEmpty($title)
            ? WidgetHelper::getWpWidget($this->getWidgetType())->name
            : $title;
    }

    public function get(mixed $id): self
    {
        $this->buildMap();

        if (array_key_exists($id, $this->map)) {
            return $this->resultToEntity($this->map[$id]->toArray());
        }

        $this->entityNotFound('theme_widget', $id);
    }

    protected function buildMap(): void
    {
        $this->map = [];

        $sideBars = ThemeSidebarHelper::getSideBarsIds();

        foreach ($sideBars as $sideBarId) {
            $sideBarWidgets = WidgetHelper::getSideBarWidgets($sideBarId);
            if (!is_array($sideBarWidgets)) {
                continue;
            }
            foreach ($sideBarWidgets as $position => $widgetId) {
                $widget = WidgetHelper::getWidget($widgetId);

                if (StringHelper::isNullOrEmpty($widget->getType())) {
                    continue;
                }

                if (is_null($widget->getPk())) {
                    $widget->setSideBar($sideBarId);
                    $widget->setSideBarPosition($position);
                    $widget->write();
                    $widget = WidgetHelper::getWidget($widgetId);
                }

                $widget->setSideBar($sideBarId);
                $widget->setSideBarPosition($position);
                $this->map[$widget->getPk()] = $widget;
            }
        }
    }

    /**
     * @return WidgetEntity[]
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
        foreach ($this->map as $widget) {
            $stateArray = $widget->toArray();
            $collection[] = $this->resultToEntity($stateArray);
        }

        return array_slice($collection, $offset, $limit);
    }

    public function getTotal(): int
    {
        $this->buildMap();

        return (count($this->map));
    }

    private function instanceToArray(WidgetEntity $entity): array
    {
        return [
            'widgetType'  => $entity->getWidgetType(),
            'index'       => $entity->getIndex(),
            'settings'    => $entity->getSettings(),
            'bar'         => $entity->getBar(),
            'barPosition' => $entity->getBarPosition(),
        ];
    }

    public function toArray(): array
    {
        return $this->instanceToArray($this);
    }

    private function getWidgetHelperInstance(self $entity): WidgetHelper
    {
        if (
            $entity->getPK() > 0
            && !array_key_exists(WidgetHelper::SMARTLING_IDENTITY_FIELD_NAME, $entity->getSettings())
        ) {
            $settings = $entity->getSettings();
            $settings[WidgetHelper::SMARTLING_IDENTITY_FIELD_NAME] = $entity->getPK();
            $entity->setSettings($settings);
        }

        return WidgetHelper::fromArray($this->instanceToArray($entity));
    }

    /**
     * Stores entity to database
     * @param Entity $entity
     * @return int
     */
    public function set(Entity $entity): int
    {
        if (!$entity instanceof self) {
            throw new \InvalidArgumentException("WidgetEntity->set() must be called with WidgetEntity");
        }
        $widgetHelper = $this->getWidgetHelperInstance($entity);
        $widgetHelper->write();

        return $widgetHelper->getPk();
    }

    /**
     * @return array
     */
    protected function getNonCloneableFields(): array
    {
        return [$this->getPrimaryFieldName()];
    }

    /**
     * @return string
     */
    public function getPrimaryFieldName(): string
    {
        return 'id';
    }

    /**
     * Converts instance of EntityAbstract to array to be used for BulkSubmit screen
     */
    public function toBulkSubmitScreenRow(): BulkSubmitScreenRow
    {
        return new BulkSubmitScreenRow($this->getId(),
            sprintf('"%s" on %s (position %d)', $this->getTitle(), ThemeSidebarHelper::getSideBarLabel($this->getBar()), $this->getBarPosition()), $this->getType(),
        );
    }
}