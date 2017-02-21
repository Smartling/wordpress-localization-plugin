<?php

namespace Smartling\Helpers\MetaFieldProcessor;

use Smartling\ContentTypes\ConfigParsers\FieldFilterConfigParser;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class CustomFieldFilterHandler
{
    /**
     * @param ContainerBuilder $di
     * @param array            $config
     */
    public static function registerFilter(ContainerBuilder $di, array $config)
    {

        $parser = new FieldFilterConfigParser($config);
        $parser->parse();


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

}