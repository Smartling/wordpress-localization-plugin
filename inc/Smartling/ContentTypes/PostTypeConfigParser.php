<?php

namespace Smartling\ContentTypes;

use Smartling\Helpers\StringHelper;

/**
 * Class CustomPostTypeConfigParser
 * @package Smartling\ContentTypes
 */
class PostTypeConfigParser
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
     * PostTypeConfigParser constructor.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->setRawConfig($config);
        $this->validate();
    }

    private function validateType($config)
    {
        if (is_string($config)) {
            $config = ['identifier' => $config];
        }

        $label = array_key_exists('label', $config) ? $config['label'] : '';

        $typeTemplate = [
            'identifier' => 'unknown',
            'label'      => '',
            'widget'     => [
                'visible' => false,
                'message' => vsprintf('No original %s found', [$label]),
            ],
            'visibility' => [
                'submissionBoard' => true,
                'bulkSubmit'      => true,
            ],
        ];

        $config = array_replace_recursive($typeTemplate, $config);

        if ('unknown' !== $config['identifier']) {
            $this->setIdentifier($config['identifier']);
        }
        if (!StringHelper::isNullOrEmpty($config['label'])) {
            $this->setLabel($config['label']);
        }
        $this->setWidgetVisible($config['widget']['visible']);
        $this->setWidgetMessage($config['widget']['message']);
        $this->setVisibility($config['visibility']);
    }

    private function validate()
    {
        $rawConfig = $this->getRawConfig();
        if (array_key_exists('type', $rawConfig)) {
            $this->validateType($rawConfig['type']);
        }
    }

    public function isValidType()
    {
        return (!StringHelper::isNullOrEmpty($this->getIdentifier()));
    }

    public function hasWidget()
    {
        return $this->isValidType() && true === $this->getWidgetMessage();
    }
}