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
        $di->get('logger')->debug(vsprintf('Registering filter for config: %s',[var_export($config, true)]));
        $parser = new FieldFilterConfigParser($config, $di);
        $di->get('logger')->debug(vsprintf('Validating filter...',[]));
        $isValid = $parser->getValidFiler();
        if (true === $isValid) {
            $filter = $parser->getFilter();
            $di->get('logger')->debug(vsprintf('Adding filter for config: %s',[var_export($config, true)]));
            $di->get('meta-field.processor.manager')->registerProcessor($filter);
        } else {
            $di->get('logger')->warning(vsprintf('Filter isn\'t valid for config: %s',[var_export($config, true)]));
            $di->get('logger')->warning(vsprintf('Filter isn\'t added.',[]));
        }
    }
}