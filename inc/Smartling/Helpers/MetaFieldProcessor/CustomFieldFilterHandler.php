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
        self::getLogger()->debug(vsprintf('Validating filter...', []));
        $isValid = $parser->getValidFiler();
        if (true === $isValid) {
            static::$filters[] = $config;
            $filter = $parser->getFilter();
            self::getLogger()->debug(vsprintf('Adding filter for config: %s', [var_export($config, true)]));
            $di->get('meta-field.processor.manager')->registerProcessor($filter);
        } else {
            self::getLogger()->warning
            (vsprintf('Filter isn\'t added. Filter isn\'t valid for config: %s', [var_export($config, true)])
            );
        }
    }

    public static function getProcessor(ContainerBuilder $di, array $config)
    {
        $parser = new FieldFilterConfigParser($config, $di);
        self::getLogger()->warning(vsprintf('looking for processor for config: %s', [var_export($config, true)]));
        if (true === $isValid = $parser->getValidFiler()) {
            return $parser->getFilter();
        }

        return false;
    }

}