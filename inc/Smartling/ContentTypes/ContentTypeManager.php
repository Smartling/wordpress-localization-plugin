<?php

namespace Smartling\ContentTypes;

use Smartling\Exception\SmartlingInvalidFactoryArgumentException;
use Smartling\Processors\SmartlingFactoryAbstract;

/**
 * Class ContentTypeManager
 * @package Smartling\ContentTypes
 */
class ContentTypeManager extends SmartlingFactoryAbstract
{

    private static $baseTypes = ['post', 'taxonomy', 'virtual'];

    /**
     * ContentTypeManager constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->message = 'Requested descriptor for \'%s\' that doesn\'t exists.';
        $this->setAllowDefault(false);
    }

    /**
     * @param ContentTypeInterface $descriptor
     */
    public function addDescriptor(ContentTypeInterface $descriptor)
    {
        $this->registerHandler($descriptor->getSystemName(), $descriptor);
    }

    /**
     * @param string $systemName
     *
     * @return ContentTypeInterface
     */
    public function getDescriptorByType($systemName)
    {
        return $this->getHandler($systemName);
    }

    /**
     * @param string $baseType
     *
     * @return ContentTypeInterface[]
     */
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

    /**
     * @return array
     */
    public function getRegisteredContentTypes()
    {
        return array_keys($this->getCollection());
    }

    public function getRestrictedForBulkSubmit()
    {
        $output = [];


            foreach ($this->getCollection() as $descriptor) {
                /**
                 * @var ContentTypeInterface $descriptor
                 */
                if (false === $descriptor->getVisibility()['bulkSubmit']) {
                    $output[] = $descriptor->getSystemName();
                }
            }


        return $output;
    }

    public function getHandler($contentType)
    {
        if (array_key_exists($contentType, $this->getCollection())) {
            return $this->getCollection()[$contentType];
        } else {
            if (true === $this->getAllowDefault() && null !== $this->getDefaultHandler()) {
                return $this->getDefaultHandler();
            } else {
                $message = vsprintf($this->message, [$contentType, get_called_class()]);
                throw new SmartlingInvalidFactoryArgumentException($message);
            }
        }
    }
}