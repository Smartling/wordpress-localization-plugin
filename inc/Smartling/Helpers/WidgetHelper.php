<?php

namespace Smartling\Helpers;

/**
 * Class WidgetHelper
 *
 * @package Smartling\Helpers
 */
class WidgetHelper
{
    /**
     * key for generated IDs
     */
    const SMARTLING_IDENTITY_FIELD_NAME = 'smartlingId';

    /**
     * @var integer
     */
    private $index = 0;

    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $sideBar = '';

    /**
     * @var int
     */
    private $sideBarPosition = 0;

    /**
     * @var array
     */
    private $settings = [];

    /**
     * @param $widgetId
     */
    public function __construct($widgetId)
    {
        $this->parseWidgetId($widgetId);
        $this->read();
    }

    /**
     * @param string $widgetId
     */
    private function parseWidgetId($widgetId)
    {
        $parts = explode('-', $widgetId);
        $this->setIndex((int)end($parts));
        unset($parts[count($parts) - 1]);
        $this->setType(implode('-', $parts));
    }

    /**
     * @return void
     */
    public function read()
    {
        $optionValue = OptionHelper::get($this->getOptionName(), false);

        if (!is_array($optionValue)) {
            $optionValue = [$optionValue];
        }

        $settings = ((false === $optionValue) || !array_key_exists($this->getIndex(), $optionValue))
            ? []
            : $optionValue[$this->getIndex()];

        $this->setSettings($settings);
    }

    /**
     * @return string
     */
    protected function getOptionName()
    {
        return vsprintf('widget_%s', [$this->getType()]);
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
     * @return int
     */
    public function getIndex()
    {
        return $this->index;
    }

    /**
     * @param int $index
     */
    public function setIndex($index)
    {
        $this->index = $index;
    }

    public static function getWidget($widgetId)
    {
        $widgetInstance = new self($widgetId);
        if (is_null($widgetInstance->getPk())) {
            $widgetInstance->write(); // will assign ID
            $widgetInstance = new self($widgetId);
        }

        return $widgetInstance;
    }

    /**
     * @return int|null
     */
    public function getPk()
    {
        return array_key_exists(self::SMARTLING_IDENTITY_FIELD_NAME, $this->getSettings())
            ? $this->getSettings()[self::SMARTLING_IDENTITY_FIELD_NAME]
            : null;
    }

    /**
     * @return array
     */
    public function getSettings()
    {
        return $this->settings ? : [];
    }

    /**
     * @param array $settings
     */
    public function setSettings($settings)
    {
        $this->settings = $settings;
    }

    /**
     * @return void
     */
    public function write()
    {
        $widgetCollection = OptionHelper::get($this->getOptionName());
        $this->setSettings(self::tryFixSmartlingId($this->getSettings()));
        if (false === $widgetCollection) {
            $widgetCollection = [];
        }
        $widgetCollection[$this->getIndex()] = $this->getSettings();

        if (!StringHelper::isNullOrEmpty($this->getType()) || 0 === $this->getIndex()) {
            OptionHelper::set($this->getOptionName(), $widgetCollection);

            if (!StringHelper::isNullOrEmpty($this->getSideBar())) {
                $this->placeWidgetToBar();
            }
        }


    }

    private static function tryFixSmartlingId($settings)
    {
        if (!is_array($settings)) {
            $settings = [$settings];
        }

        if (!array_key_exists(self::SMARTLING_IDENTITY_FIELD_NAME, $settings)) {
            $settings[self::SMARTLING_IDENTITY_FIELD_NAME] = self::generateSmartlingId();
        }

        return $settings;
    }

    /**
     * @return int
     */
    private static function generateSmartlingId()
    {
        $key = 'SmartlingWidgetLastAutoincrementValue';

        $rawResult = OptionHelper::get($key, 1);
        $result = (int)$rawResult;
        $rawResult++;
        OptionHelper::set($key, $rawResult);

        return $result;
    }

    /**
     * @return string
     */
    public function getSideBar()
    {
        return $this->sideBar;
    }

    /**
     * @param string $sideBar
     */
    public function setSideBar($sideBar)
    {
        $this->sideBar = $sideBar;
    }

    private function placeWidgetToBar()
    {
        $bar = in_array($this->getSideBar(), ThemeSidebarHelper::getSideBarsIds(), true)
            ? $this->getSideBar()
            : ThemeSidebarHelper::INACTIVE_BAR_ID;

        $barPosition = ThemeSidebarHelper::INACTIVE_BAR_ID === $bar
            ? null
            : $this->getSideBarPosition();

        $widgetId = $this->getWidgetId();

        $barConfig = self::cleanBarFromWidget($bar, $widgetId);

        if (is_null($barPosition)) {
            $barConfig[] = $widgetId;
        } else {
            $barConfig[$barPosition] = $widgetId;
        }

        self::writeSidebarsWidgets($bar, $barConfig);
    }

    /**
     * @return int
     */
    public function getSideBarPosition()
    {
        return $this->sideBarPosition;
    }

    /**
     * @param int $sideBarPosition
     */
    public function setSideBarPosition($sideBarPosition)
    {
        $this->sideBarPosition = $sideBarPosition;
    }

    /**
     * @return string
     */
    private function getWidgetId()
    {
        return vsprintf('%s-%s', [$this->getType(), $this->getIndex()]);
    }

    /**
     * @param $barId
     * @param $widgetId
     *
     * @return array
     */
    private static function cleanBarFromWidget($barId, $widgetId)
    {
        $content = array_flip(static::getSideBarWidgets($barId));

        if (array_key_exists($widgetId, $content)) {
            unset($content[$widgetId]);
        }

        return array_flip($content);
    }

    /**
     * @param $sideBadId
     *
     * @return array
     */
    public static function getSideBarWidgets($sideBadId)
    {
        $totalWidgetData = wp_get_sidebars_widgets();

        return array_key_exists($sideBadId, $totalWidgetData) ? $totalWidgetData[$sideBadId] : [];
    }

    private static function writeSidebarsWidgets($barId, array $widgets = [])
    {
        $config = self::readSidebarsWidgetsTotal();

        unset ($config[$barId]);

        foreach ($widgets as $pos => $widgetId) {
            foreach ($config as $bar => $widgetSet) {
                $config[$bar] = self::cleanBarFromWidget($bar, $widgetId);
            }
        }

        $config[$barId] = $widgets;

        self::writeSidebarsWidgetsTotal($config);
    }

    /**
     * @return array
     */
    private static function readSidebarsWidgetsTotal()
    {
        return wp_get_sidebars_widgets();
    }

    /**
     * @param $sidebars
     */
    private static function writeSidebarsWidgetsTotal($sidebars)
    {
        wp_set_sidebars_widgets($sidebars);
    }

    /**
     * @param array $state
     *
     * @return WidgetHelper
     */
    public static function fromArray(array $state)
    {
        $instance = new self($state['widgetType'] . '-' . $state['index']);
        $instance->setSettings($state['settings']);
        $instance->setSideBar($state['bar']);
        $instance->setSideBarPosition($state['barPosition']);

        return $instance;
    }

    /**
     * @param $widgetType
     *
     * @return string | null
     */
    public static function getWidgetName($widgetType)
    {
        global $wp_widget_factory;

        $id_base_to_widget_class_map = array_combine(
            wp_list_pluck($wp_widget_factory->widgets, 'id_base'),
            array_keys($wp_widget_factory->widgets)
        );

        if (!isset($id_base_to_widget_class_map[$widgetType])) {
            return null;
        }

        return $wp_widget_factory->widgets[$id_base_to_widget_class_map[$widgetType]];
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'id'          => $this->getSettings()[self::SMARTLING_IDENTITY_FIELD_NAME],
            'widgetType'  => $this->getType(),
            'index'       => $this->getIndex(),
            'bar'         => $this->getSideBar(),
            'barPosition' => $this->getSideBarPosition(),
            'settings'    => $this->getSettings(),
        ];
    }
}