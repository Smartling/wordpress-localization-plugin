<?php

namespace KPS3\Smartling\Elementor;

use Smartling\Helpers\Serializers\SerializationManager;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ElementorAutoSetup implements RunnableInterface {
    public static function register(ContainerBuilder $di): void
    {
        $obj = new static();

        $action = is_admin() ? 'admin_init' : 'init';
        add_action($action, static function () use ($obj) {
            $obj->run();
        }, 99);

        /**
         * Add the Elementor Data Serializer to the serializers
         */
        /** @var SerializationManager $serializers */
        $serializers = $di->get('manager.serializer');
        $serializers->addSerializer((new ElementorDataSerializer()));

        $di->set('fields-filter.helper', new ElementorFieldsFilterHelper($di->get('manager.settings'), $di->get('acf.dynamic.support')));
        ElementorFilter::register();
        ElementorProcessor::register();
    }

    public function run(): void
    {
    }
}
