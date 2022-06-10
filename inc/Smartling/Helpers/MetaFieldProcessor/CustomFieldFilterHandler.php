<?php

namespace Smartling\Helpers\MetaFieldProcessor;

use Smartling\ContentTypes\ConfigParsers\FieldFilterConfigParser;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Vendor\Symfony\Component\DependencyInjection\ContainerBuilder;

class CustomFieldFilterHandler
{
    public static $filters = [];

    private static function getLogger()
    {
        return MonologWrapper::getLogger(__CLASS__);
    }

    /**
     * @param ContainerBuilder $di
     * @param array            $config
     */
    public static function registerFilter(ContainerBuilder $di, array $config)
    {
        self::getLogger()->debug(vsprintf('Registering filter for config: %s', [var_export($config, true)]));
        $parser = new FieldFilterConfigParser($config, $di);
        self::getLogger()->debug('Validating filter...');
        if (true === $parser->isValidFiler()) {
            static::$filters[] = $config;
            $filter = $parser->getFilter();
            self::getLogger()->debug('Adding filter...');
            $di->get('meta-field.processor.manager')->registerProcessor($filter);
        }
    }

    public static function getProcessor(ContainerBuilder $di, array $config)
    {
        $parser = new FieldFilterConfigParser($config, $di);
        self::getLogger()->debug(vsprintf('looking for processor for config: %s', [var_export($config, true)]));
        if (true === $parser->isValidFiler()) {
            return $parser->getFilter();
        }

        return false;
    }

}