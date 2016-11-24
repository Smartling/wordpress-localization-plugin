<?php

namespace Smartling\ContentTypes;

use Psr\Log\LoggerInterface;
use Smartling\Processors\SmartlingFactoryAbstract;

/**
 * Class ContentTypeManager
 * @package Smartling\ContentTypes
 */
class ContentTypeManager extends SmartlingFactoryAbstract
{

    private static $baseTypes = ['post', 'taxonomy', 'virtual'];

    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);
        $this->setAllowDefault(false);
    }

    public function addDescriptor(ContentTypeInterface $descriptor)
    {
        $this->registerHandler($descriptor->getSystemName(), $descriptor);
    }

    public function getDescriptorByType($systemName)
    {
        return $this->getHandler($systemName);
    }

    public function getDescriptorsByBaseType($baseType)
    {
        $output = [];

        if (in_array($baseType, self::$baseTypes, true)) {
            foreach ($this->getCollection() as $descriptor) {
                if ($this->checkBaseType($descriptor, $baseType)) {
                    $output[] = $this->getDescriptorByType($descriptor->getSystemName());
                }
            }
        }

        return $output;
    }

    /**
     * @param ContentTypeInterface $descriptor
     * @param string               $type see: self::$baseTypes
     *
     * @return bool
     */
    private function checkBaseType(ContentTypeInterface $descriptor, $type)
    {
        return true === call_user_func_array([$descriptor, 'is' . ucfirst($type)], []);
    }
}