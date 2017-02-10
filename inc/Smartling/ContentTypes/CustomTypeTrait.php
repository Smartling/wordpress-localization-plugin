<?php

namespace Smartling\ContentTypes;

use Smartling\Bootstrap;
use Smartling\ContentTypes\ConfigParsers\ConfigParserInterface;
use Smartling\Exception\SmartlingInvalidFactoryArgumentException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class CustomTypeTrait
 * @package Smartling\ContentTypes
 */
trait CustomTypeTrait
{
    /**
     * @var string
     */
    private $systemName = '';

    /**
     * @var array
     */
    private $config = [];

    /**
     * @var string
     */
    private $label = '';

    /**
     * @var ConfigParserInterface
     */
    private $configParser;

    /**
     * @return string
     */
    public function getSystemName()
    {
        return $this->systemName;
    }

    /**
     * @param string $systemName
     */
    public function setSystemName($systemName)
    {
        $this->systemName = $systemName;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param array $config
     *
     * @return ContentTypeInterface
     */
    public function setConfig($config)
    {
        $this->config = $config;

        return $this;
    }

    /**
     * Display name of content type, e.g.: Post
     *
     * @param string $default
     *
     * @return string
     */
    public function getLabel($default = 'unknown')
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
     * @return ConfigParserInterface
     */
    public function getConfigParser()
    {
        return $this->configParser;
    }

    /**
     * @param ConfigParserInterface $configParser
     */
    public function setConfigParser($configParser)
    {
        $this->configParser = $configParser;
    }

    /**
     * @return bool
     */
    public function isValidType()
    {
        if (!$this->getConfigParser()->isValid()) {
            return false;
        }

        /**
         * Check if identifier already registered
         */
        $mgr = $this->getContainerBuilder()->get('content-type-descriptor-manager');

        /**
         * @var ContentTypeManager $mgr
         */
        $registered = null;
        try {
            $mgr->getDescriptorByType($this->getSystemName());

            return false;
        } catch (SmartlingInvalidFactoryArgumentException $e) {
            // all ok,  do nothing
        }

        return true;
    }

    /**
     * @param ContainerBuilder $di
     * @param array            $config
     */
    public static function registerCustomType(ContainerBuilder $di, array $config)
    {
        $manager = 'content-type-descriptor-manager';

        $descriptor = new static($di);
        $descriptor->setConfig($config);
        $descriptor->validateConfig();

        if ($descriptor->isValidType()) {
            $descriptor->registerIOWrapper();
            $descriptor->registerWidgetHandler();
            $mgr = $di->get($manager);
            /**
             * @var \Smartling\ContentTypes\ContentTypeManager $mgr
             */
            $mgr->addDescriptor($descriptor);
        }
        $descriptor->registerFilters();
    }

    /**
     * @return array [
     *  'submissionBoard'   => true|false,
     *  'bulkSubmit'        => true|false
     * ]
     */
    public function getVisibility()
    {
        return $this->getConfigParser()->getVisibility();
    }
}