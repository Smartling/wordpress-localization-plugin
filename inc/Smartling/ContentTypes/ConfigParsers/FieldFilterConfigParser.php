<?php

namespace Smartling\ContentTypes\ConfigParsers;

use Smartling\Helpers\MetaFieldProcessor\BulkProcessors\CustomTypeProcessor;
use Smartling\Helpers\MetaFieldProcessor\BulkProcessors\MediaBasedProcessor;
use Smartling\Helpers\MetaFieldProcessor\BulkProcessors\PostBasedProcessor;
use Smartling\Helpers\MetaFieldProcessor\BulkProcessors\TermBasedProcessor;
use Smartling\Helpers\MetaFieldProcessor\CloneValueFieldProcessor;
use Smartling\Helpers\MetaFieldProcessor\MetaFieldProcessorInterface;
use Smartling\Helpers\MetaFieldProcessor\SkipFieldProcessor;
use Smartling\Helpers\Serializers\SerializerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

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
     * @var string
     */
    private $filterType;

    /**
     * bool
     */
    private $validFiler;

    /**
     * @var ContainerBuilder
     */
    private $di;

    private function getService($serviceName)
    {
        return $this->getDi()->get($serviceName);
    }

    /**
     * @return ContainerBuilder
     */
    public function getDi()
    {
        return $this->di;
    }

    /**
     * @param ContainerBuilder $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return mixed
     */
    public function getValidFiler()
    {
        return $this->validFiler;
    }

    /**
     * @param mixed $validFiler
     */
    public function setValidFiler($validFiler)
    {
        $this->validFiler = $validFiler;
    }

    /**
     * @return string
     */
    public function getFilterType()
    {
        return $this->filterType;
    }

    /**
     * @param string $filterType
     */
    public function setFilterType($filterType)
    {
        $this->filterType = $filterType;
    }

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
                return $this->validateSerialization() && $this->validateValueType() && $this->validateRelatedType();
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
        if (null !== ($value = $this->getConfigParam('type'))) {
            $this->setFilterType($value);

            return true;
        } else {
            return false;
        }
    }


    /**
     * @return mixed
     */
    public function parse()
    {
        $result = $this->validatePattern() && $this->validateAction();

        $this->setValidFiler($result);
    }


    /**
     * ConfigParserAbstract constructor.
     *
     * @param array $config
     */
    public function __construct(array $config, ContainerBuilder $di)
    {
        $this->setRawConfig($config);
        $this->setDi($di);
        $this->parse();
    }

    /**
     * @return SerializerInterface
     */
    private function getSerializerInstance()
    {
        return $this->getService('manager.serializer')->getSerializer($this->getSerialization());
    }

    private function getLocalizeFilter()
    {
        $serializer = $this->getSerializerInstance();

        // currently supporting only reference value type (id)

        if (self::VALUE_TYPE_REFERENCE !== $this->getValueType()) {
            throw new \InvalidArgumentException('Currently only \'reference\' is supported.');
        }


        switch ($this->getFilterType()) {
            case 'term':
            case 'taxonomy':
                $filter = new TermBasedProcessor(
                    $this->getService('logger'),
                    $this->getService('translation.helper'),
                    $this->getPattern()
                );

                $filter->setContentHelper($this->getService('content.helper'));
                $filter->setSerializer($serializer);

                return $filter;

                break;
            case 'post':

                $filter = new PostBasedProcessor(
                    $this->getService('logger'),
                    $this->getService('translation.helper'),
                    $this->getPattern()
                );

                $filter->setContentHelper($this->getService('content.helper'));
                $filter->setSerializer($serializer);

                return $filter;

                break;
            case 'media':

                $filter = new MediaBasedProcessor(
                    $this->getService('logger'),
                    $this->getService('translation.helper'),
                    $this->getPattern()
                );

                $filter->setContentHelper($this->getService('content.helper'));
                $filter->setSerializer($serializer);

                return $filter;

                // reference to any attachment
                break;
            default:
                $filter = new CustomTypeProcessor(
                    $this->getService('logger'),
                    $this->getService('translation.helper'),
                    $this->getPattern(),
                    $this->getFilterType()
                );

                $filter->setContentHelper($this->getService('content.helper'));
                $filter->setSerializer($serializer);

                return $filter;
                break;
        }
    }

    /**
     * @return MetaFieldProcessorInterface
     */
    public function getFilter()
    {
        switch ($this->getAction()) {
            case self::ACTION_SKIP:
                return new SkipFieldProcessor($this->getPattern());
                break;
            case self::ACTION_COPY:
                return new CloneValueFieldProcessor($this->getPattern(), $this->getService('content.helper'), $this->getService('logger'));
                break;
            case self::ACTION_LOCALIZE:
                return $this->getLocalizeFilter();
                break;
            default:
                $this->getService('logger')->error(vsprintf('Invalid filter action: \'%s\'', [$this->getAction()]));
                die ($this->getAction());
        }
    }
}
