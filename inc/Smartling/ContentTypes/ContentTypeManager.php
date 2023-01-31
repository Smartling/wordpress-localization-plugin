<?php

namespace Smartling\ContentTypes;

use Smartling\Exception\SmartlingConfigException;
use Smartling\Exception\SmartlingInvalidFactoryArgumentException;
use Smartling\Processors\SmartlingFactoryAbstract;

class ContentTypeManager extends SmartlingFactoryAbstract
{
    public const VIRTUAL = 'virtual';
    private static array $baseTypes = ['post', 'taxonomy', self::VIRTUAL];

    public function __construct(bool $allowDefault = false, ?object $defaultHandler = null)
    {
        parent::__construct($allowDefault, $defaultHandler);
        $this->message = 'Requested descriptor for \'%s\' that doesn\'t exists.';
    }

    public function addDescriptor(ContentTypeInterface $descriptor): void
    {
        $this->registerHandler($descriptor->getSystemName(), $descriptor);
    }

    public function getDescriptorByType(string $systemName): ContentTypeInterface
    {
        return $this->getHandler($systemName);
    }

    /**
     * @return ContentTypeInterface[]
     */
    public function getDescriptorsByBaseType(string $baseType): array
    {
        $output = [];

        if (in_array($baseType, self::$baseTypes, true)) {
            foreach ($this->collection as $descriptor) {
                if ($this->isBaseType($descriptor, $baseType)) {
                    $output[] = $this->getDescriptorByType($descriptor->getSystemName());
                }
            }
        }

        return $output;
    }

    private function isBaseType(ContentTypeInterface $descriptor, string $type): bool
    {
        return true === call_user_func_array([$descriptor, 'is' . ucfirst($type)], []);
    }

    public function getRegisteredContentTypes(): array
    {
        return array_keys($this->collection);
    }

    public function getRestrictedForBulkSubmit(): array
    {
        $output = [];
        foreach ($this->collection as $descriptor) {
            if (!$descriptor instanceof ContentTypeInterface) {
                throw new SmartlingConfigException(ContentTypeInterface::class . ' expected');
            }
            if (!$descriptor->isVisible('bulkSubmit')) {
                $output[] = $descriptor->getSystemName();
            }
        }

        return $output;
    }

    public function getHandler(string $contentType): ContentTypeInterface
    {
        if (array_key_exists($contentType, $this->collection)) {
            return $this->collection[$contentType];
        }

        throw new SmartlingInvalidFactoryArgumentException(sprintf($this->message, $contentType, get_called_class()));
    }
}
