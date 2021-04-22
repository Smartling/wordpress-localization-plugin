<?php

namespace Smartling\ContentTypes;

use Smartling\DbAl\WordpressContentEntities\CustomizerEntity;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ContentTypeCustomizer extends ContentTypeVirtual
{
    public function __construct(ContainerBuilder $di)
    {
        parent::__construct($di);

        $this->registerIOWrapper();
        $this->registerWidgetHandler();
        $this->registerFilters();
    }

    public function getSystemName(): string
    {
        return 'customizer';
    }

    public function getLabel(): string
    {
        return __('Customizer fields');
    }

    public static function register(ContainerBuilder $di, $manager = 'content-type-descriptor-manager'): void
    {
        $descriptor = new static($di);
        $mgr = $di->get($manager);
        if (!$mgr instanceof ContentTypeManager) {
            throw new \RuntimeException(ContentTypeManager::class . ' expected');
        }
        $mgr->addDescriptor($descriptor);
    }

    public function registerWidgetHandler(): void
    {
    }

    public function registerIOWrapper(): void
    {
        $di = $this->getContainerBuilder();
        $wrapperId = 'wrapper.entity' . $this->getSystemName();
        $definition = $di->register($wrapperId, CustomizerEntity::class);
        $definition
            ->addArgument($this->getSystemName())
            ->addArgument([]);

        $di->get('factory.contentIO')->registerHandler($this->getSystemName(), $di->get($wrapperId));

    }

    public function getVisibility(): array
    {
        return [
            'submissionBoard' => false,
            'bulkSubmit'      => true,
        ];
    }

    public function registerFilters(): void
    {
    }
}
