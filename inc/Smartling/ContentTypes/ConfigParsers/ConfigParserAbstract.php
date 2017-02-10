<?php

namespace Smartling\ContentTypes\ConfigParsers;

use Smartling\Helpers\StringHelper;

/**
 * Class ConfigParserAbstract
 * @package Smartling\ContentTypes\ConfigParsers
 */
abstract class ConfigParserAbstract implements ConfigParserInterface
{
    /**
     * @var array
     */
    private $rawConfig;

    /**
     * @var string
     */
    private $identifier = null;

    /**
     * @var string
     */
    private $label = null;

    /**
     * @var bool
     */
    private $widgetVisible = false;

    /**
     * @var string
     */
    private $widgetMessage = '';

    /**
     * @var array
     */
    private $visibility;

    /**
     * @return array
     */
    protected function getRawConfig()
    {
        return $this->rawConfig;
    }

    /**
     * @param array $rawConfig
     */
    protected function setRawConfig($rawConfig)
    {
        $this->rawConfig = $rawConfig;
    }

    /**
     * @return string
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * @param string $identifier
     */
    public function setIdentifier($identifier)
    {
        $this->identifier = $identifier;
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @param string $label
     */
    public function setLabel($label)
    {
        $this->label = $label;
    }

    /**
     * @return bool
     */
    public function isWidgetVisible()
    {
        return $this->widgetVisible;
    }

    /**
     * @param bool $widgetVisible
     */
    public function setWidgetVisible($widgetVisible)
    {
        $this->widgetVisible = $widgetVisible;
    }

    /**
     * @return string
     */
    public function getWidgetMessage()
    {
        return $this->widgetMessage;
    }

    /**
     * @param string $widgetMessage
     */
    public function setWidgetMessage($widgetMessage)
    {
        $this->widgetMessage = $widgetMessage;
    }

    /**
     * @return array
     */
    public function getVisibility()
    {
        return $this->visibility;
    }

    /**
     * @param array $visibility
     */
    public function setVisibility(array $visibility)
    {
        $this->visibility = $visibility;
    }

    /**
     * ConfigParserAbstract constructor.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->setRawConfig($config);
        $this->parse();
    }

    /**
     * @return bool
     */
    public function isValid()
    {
        return (!StringHelper::isNullOrEmpty($this->getIdentifier()));
    }

    /**
     * @return bool
     */
    public function hasWidget()
    {
        return $this->isValid() && true === $this->isWidgetVisible();
    }
}