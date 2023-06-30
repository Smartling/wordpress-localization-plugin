<?php

namespace Smartling\ContentTypes;

use Smartling\ContentTypes\ConfigParsers\ConfigParserInterface;
use Smartling\Exception\SmartlingInvalidFactoryArgumentException;
use Smartling\Vendor\Symfony\Component\DependencyInjection\ContainerBuilder;

trait CustomTypeTrait
{
    private string $systemName = '';

    private array $config = [];

    private string $label = '';

    private ConfigParserInterface $configParser;

    public function getSystemName(): string
    {
        return $this->systemName;
    }

    public function setSystemName(string $systemName): void
    {
        $this->systemName = $systemName;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config): ContentTypeInterface
    {
        $this->config = $config;

        return $this;
    }

    /**
     * Display name of content type, e.g.: Post
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getConfigParser(): ConfigParserInterface
    {
        return $this->configParser;
    }

    public function setConfigParser(ConfigParserInterface $configParser): void
    {
        $this->configParser = $configParser;
    }

    public function isValidType(): bool
    {
        if (!$this->getConfigParser()->isValid()) {
            return false;
        }

        /**
         * Check if identifier already registered
         */
        $mgr = $this->getContainerBuilder()->get('content-type-descriptor-manager');
        if (!$mgr instanceof ContentTypeManager) {
            throw new \RuntimeException("content-type-descriptor-manager expected to be " . ContentTypeManager::class);
        }

        try {
            $mgr->getDescriptorByType($this->getSystemName());

            return false;
        } catch (SmartlingInvalidFactoryArgumentException) {
            // all ok,  do nothing
        }

        return true;
    }

    public static function registerCustomType(ContainerBuilder $di, array $config): void
    {
        $manager = 'content-type-descriptor-manager';

        $descriptor = new static($di);

        $descriptor->setConfig($config);
        $descriptor->validateConfig();

        if ($descriptor->isValidType()) {
            $descriptor->registerIOWrapper();
            $descriptor->registerWidgetHandler();
            $mgr = $di->get($manager);
            if (!$mgr instanceof ContentTypeManager) {
                throw new \RuntimeException("$manager expected to be " . ContentTypeManager::class);
            }
            if (!$descriptor instanceof ContentTypeInterface) {
                throw new \RuntimeException(ContentTypeInterface::class . ' expected');
            }
            $mgr->addDescriptor($descriptor);
        }
    }

    public function isVisible(string $page): bool
    {
        return $this->getConfigParser()->getVisibility($page);
    }
}
