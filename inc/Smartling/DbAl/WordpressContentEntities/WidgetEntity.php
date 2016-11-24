<?php

namespace Smartling\DbAl\WordpressContentEntities;

use Psr\Log\LoggerInterface;
use Smartling\Helpers\StringHelper;
use Smartling\Helpers\ThemeSidebarHelper;
use Smartling\Helpers\WidgetHelper;
use Smartling\Helpers\WordpressContentTypeHelper;

/**
 * Class WidgetEntity
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
 * @package Smartling\DbAl\WordpressContentEntities
 */
class WidgetEntity extends VirtualEntityAbstract
{

    /**
     * @var WidgetHelper[] All widgets of current theme.
     */
    private $map = [];

    /**
     * Standard 'post' content-type fields
     * @var array
     */
    protected $fields = [
        'id',
        'widgetType',
        'index',
        'bar',
        'barPosition',
        'settings',
    ];

    /**
     * @inheritdoc
     */
    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);

        $this->setType(WordpressContentTypeHelper::CONTENT_TYPE_WIDGET);
        $this->hashAffectingFields = array_merge($this->hashAffectingFields, [
            'id',
            'widgetType',
            'index',
            'bar',
            'barPosition',
        ]);

        $this->setEntityFields($this->fields);
        $this->setRelatedTypes([
                                   WordpressContentTypeHelper::CONTENT_TYPE_NAV_MENU,
                                   WordpressContentTypeHelper::CONTENT_TYPE_MEDIA_ATTACHMENT,
                                   WordpressContentTypeHelper::CONTENT_TYPE_POST_TESTIMONIAL,
                               ]);
    }

    /**
     * @inheritdoc
     */
    protected function getFieldNameByMethodName($method)
    {

        $way = substr($method, 0, 3);

        $possibleField = lcfirst(substr($method, 3));

        if (in_array($way, ['set', 'get']) && in_array($possibleField, $this->fields)) {
            return $possibleField;
        } else {
            $message = vsprintf('Method %s not found in %s', [$method, __CLASS__]);
            $this->getLogger()
                ->error($message);
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
        $title = array_key_exists('title', $this->getSettings())
            ? $this->getSettings()['title'] : null;

        return StringHelper::isNullOrEmpty($title)
            ? WidgetHelper::getWidgetName($this->getWidgetType())->name
            : $title;
    }

    /**
     * Loads the entity from database
     *
     * @param $guid
     *
     * @return EntityAbstract
     */
    public function get($guid)
    {
        $this->buildMap();

        if (array_key_exists($guid, $this->map)) {
            return $this->resultToEntity($this->map[$guid]->toArray());
        } else {
            $this->entityNotFound(WordpressContentTypeHelper::CONTENT_TYPE_WIDGET, $guid);
        }
    }

    /**
     * @param string $tagName
     * @param string $tagValue
     * @param bool   $unique
     */
    public function setMetaTag($tagName, $tagValue, $unique = true)
    {
    }

    private function buildMap()
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
     * @param string $limit
     * @param int    $offset
     * @param bool   $orderBy
     * @param bool   $order
     *
     * @return mixed
     */
    public function getAll($limit = '', $offset = 0, $orderBy = false, $order = false)
    {
        $this->buildMap();
        $collection = [];
        foreach ($this->map as $widget) {
            $stateArray = $widget->toArray();
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
     * @param WidgetEntity $entity
     *
     * @return array
     */
    private function instanceToArray(WidgetEntity $entity)
    {
        return [
            'widgetType'  => $entity->getWidgetType(),
            'index'       => $entity->getIndex(),
            'settings'    => $entity->getSettings(),
            'bar'         => $entity->getBar(),
            'barPosition' => $entity->getBarPosition(),
        ];
    }

    /**
     * @param WidgetEntity $entity
     *
     * @return WidgetHelper
     */
    private function getWidgetHelperInstance(WidgetEntity $entity)
    {
        if (
            (int)$entity->getPK() > 0
            && !array_key_exists(WidgetHelper::SMARTLING_IDENTITY_FIELD_NAME, $entity->getSettings())
        ) {
            $settings = $entity->getSettings();
            $settings[WidgetHelper::SMARTLING_IDENTITY_FIELD_NAME] = (int)$entity->getPK();
            $entity->setSettings($settings);
        }

        return WidgetHelper::fromArray($this->instanceToArray($entity));
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
        /** @var WidgetEntity $entity */
        $widgetHelper = $this->getWidgetHelperInstance($entity);
        $widgetHelper->write();

        return $widgetHelper->getPk();
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

    /**
     * Converts instance of EntityAbstract to array to be used for BulkSubmit screen
     * @return array
     */
    public function toBulkSubmitScreenRow()
    {
        return [
            'id'      => $this->getId(),
            'title'   => '"' . $this->getTitle() . '" on ' . ThemeSidebarHelper::getSideBarLabel($this->getBar()) .
                         '(position ' . $this->getBarPosition() . ')',
            'type'    => $this->getType(),
            'author'  => $this->getIndex(),
            'status'  => null,
            'locales' => null,
            'updated' => null,
        ];
    }
}