<?php

namespace Smartling\ContentTypes\ConfigParsers;

use Smartling\Bootstrap;

/**
 * Class FieldFilterConfigParser
 * @package Smartling\ContentTypes\ConfigParsers
 */
class FieldFilterConfigParser
{
    /**
     * Valid actions
     */
    const ACTION_COPY     = 'copy';
    const ACTION_SKIP     = 'skip';
    const ACTION_LOCALIZE = 'localize';

    const VALUE_TYPE_REFERENCE = 'reference';
    const VALUE_TYPE_URL       = 'url';

    private $actions = [
        self::ACTION_COPY,
        self::ACTION_SKIP,
        self::ACTION_LOCALIZE,
    ];

    private $valueTypes = [
        self::VALUE_TYPE_REFERENCE,
        self::VALUE_TYPE_URL,
    ];


    /**
     * @var string
     */
    private $pattern;

    /**
     * @var string
     */
    private $action;

    /**
     * @var string
     */
    private $serialization;

    /**
     * @var string
     */
    private $valueType;

    /**
     * @var array
     */
    private $rawConfig;


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
    public function getPattern()
    {
        return $this->pattern;
    }

    /**
     * @param string $pattern
     */
    public function setPattern($pattern)
    {
        $this->pattern = $pattern;
    }

    /**
     * @return string
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * @param string $action
     */
    public function setAction($action)
    {
        $this->action = $action;
    }

    /**
     * @return string
     */
    public function getSerialization()
    {
        return $this->serialization;
    }

    /**
     * @param string $serialization
     */
    public function setSerialization($serialization)
    {
        $this->serialization = $serialization;
    }

    /**
     * @return string
     */
    public function getValueType()
    {
        return $this->valueType;
    }

    /**
     * @param string $valueType
     */
    public function setValueType($valueType)
    {
        $this->valueType = $valueType;
    }

    private function getConfigParam($paramName)
    {
        $config = $this->getRawConfig();
        if (array_key_exists($paramName, $config)) {
            return $config[$paramName];
        }

        return null;
    }

    private function validatePattern()
    {
        if (null !== ($pattern = $this->getConfigParam('pattern'))) {
            $this->setPattern($pattern);

            return true;
        } else {
            return false;
        }
    }

    private function validateAction()
    {
        if (null !== ($action = $this->getConfigParam('action')) && in_array($action, $this->actions)) {
            $this->setAction($action);
            if (self::ACTION_LOCALIZE === $this->getAction()) {
                return $this->validateSerialization() && $this->validateValueType();
            } else {
                return true;
            }
        }

    }

    private function validateSerialization()
    {
        if (null !== ($serialization = $this->getConfigParam('serialization'))) {
            $this->setSerialization($serialization);

            return true;
        } else {
            return false;
        }

    }

    private function validateValueType()
    {
        if (null !== ($value = $this->getConfigParam('value')) && in_array($value, $this->valueTypes)) {
            $this->setValueType($value);

            return true;
        } else {
            return false;
        }
    }

    private function validateRelatedType()
    {

    }


    /**
     * @return mixed
     */
    public function parse()
    {
        $result = $this->validatePattern() && $this->validateAction();

        Bootstrap::DebugPrint($this->getRawConfig(), true);
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


}
