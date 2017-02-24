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
        $parser = new FieldFilterConfigParser($config, $di);
        if (true === $parser->getValidFiler()) {
            $filter = $parser->getFilter();
            $di->get('meta-field.processor.manager')->registerProcessor($filter);
        }
    }
}