<?php

namespace Smartling\Services;

use Smartling\Bootstrap;
use Smartling\Helpers\SimpleStorageHelper;
use Symfony\Component\Yaml\Parser;

/**
 * Class GlobalSettingsManager
 * @package Smartling\Services
 */
class GlobalSettingsManager
{

    /**
     * Disable on-boot self-diagnostics (not recommended)
     */
    const SELF_CHECK_IDENTIFIER         = 'smartling_static_check_disabled';
    const SELF_CHECK_IDENTIFIER_DEFAULT = 0;

    public static function getSkipSelfCheckDefault()
    {
        return static::SELF_CHECK_IDENTIFIER_DEFAULT;
    }

    public static function getSkipSelfCheck()
    {
        return SimpleStorageHelper::get(static::SELF_CHECK_IDENTIFIER, static::getSkipSelfCheckDefault());
    }

    public static function setSkipSelfCheck($value)
    {
        SimpleStorageHelper::set(static::SELF_CHECK_IDENTIFIER, $value);

        if (static::getSkipSelfCheckDefault() === (int)$value) {
            SimpleStorageHelper::drop(static::SELF_CHECK_IDENTIFIER);
        }
    }

    /**
     * Disable logging (not recommended)
     */
    const DISABLE_LOGGING         = 'smartling_disable_logging';
    const DISABLE_LOGGING_DEFAULT = 0;

    public static function getDisableLoggingDefault()
    {
        return static::DISABLE_LOGGING_DEFAULT;
    }

    public static function getDisableLogging()
    {
        return SimpleStorageHelper::get(static::DISABLE_LOGGING, static::getDisableLoggingDefault());
    }

    public static function setDisableLogging($value)
    {
        SimpleStorageHelper::set(static::DISABLE_LOGGING, $value);

        if (static::getDisableLoggingDefault() === (int)$value) {
            SimpleStorageHelper::drop(static::DISABLE_LOGGING);
        }
    }

    /**
     * Disable built-in ACF-Pro plugin support
     */
    const DISABLE_ACF         = 'smartling_disable_acf';
    const DISABLE_ACF_DEFAULT = 0;

    public static function getDisableACFDefault()
    {
        return static::DISABLE_ACF_DEFAULT;
    }

    public static function getDisableACF()
    {
        return SimpleStorageHelper::get(static::DISABLE_ACF, static::getDisableACFDefault());
    }

    public static function setDisableACF($value)
    {
        SimpleStorageHelper::set(static::DISABLE_ACF, $value);

        if (static::getDisableACFDefault() === (int)$value) {
            SimpleStorageHelper::drop(static::DISABLE_ACF);
        }
    }

    /**
     * Disable built-in ACF-Pro Database definitions lookup support (not recommended)
     */
    const DISABLE_ACF_DB_LOOKUP         = 'smartling_disable_db_lookup';
    const DISABLE_ACF_DB_LOOKUP_DEFAULT = 0;

    public static function getDisableAcfDbLookupDefault()
    {
        return static::DISABLE_ACF_DB_LOOKUP_DEFAULT;
    }

    public static function getDisableAcfDbLookup()
    {
        return SimpleStorageHelper::get(static::DISABLE_ACF_DB_LOOKUP, static::getDisableAcfDbLookupDefault());
    }

    public static function setDisableAcfDbLookup($value)
    {
        SimpleStorageHelper::set(static::DISABLE_ACF_DB_LOOKUP, $value);

        if (static::getDisableAcfDbLookupDefault() === (int)$value) {
            SimpleStorageHelper::drop(static::DISABLE_ACF_DB_LOOKUP);
        }
    }

    /**
     * Log file name customization
     */
    const SMARTLING_CUSTOM_LOG_FILE = 'smartling_log_file';

    public static function getLogFileSpecDefault()
    {
        return $defaultLogFileName = Bootstrap::getLogFileName(false, true);
    }

    public static function getLogFileSpec()
    {
        return SimpleStorageHelper::get(static::SMARTLING_CUSTOM_LOG_FILE, static::getLogFileSpecDefault());
    }

    public static function setLogFileSpec($value)
    {
        SimpleStorageHelper::set(static::SMARTLING_CUSTOM_LOG_FILE, $value);

        if (static::getLogFileSpecDefault() === $value) {
            SimpleStorageHelper::drop(static::SMARTLING_CUSTOM_LOG_FILE);
        }
    }

    /**
     * Logging configuration customization
     */
    const LOGGING_CUSTOMIZATION = 'smartling_logging_customization';

    public static function getLoggingCustomizationDefault()
    {
        return Bootstrap::getContainer()->getParameter('logger.filter.default');
    }

    public static function getLoggingCustomization()
    {
        return SimpleStorageHelper::get(static::LOGGING_CUSTOMIZATION, static::getLoggingCustomizationDefault());
    }

    private static function parseYamlData($yamlData)
    {
        $parser = new Parser();
        $data   = null;
        try {
            $data = $parser->parse($yamlData, true);
        } catch (\Exception $e) {
            Bootstrap::getLogger()->warning(vsprintf('Failed parsing new value: "%s"', [var_export($yamlData, true)]));
        }
        return $data;
    }

    public static function setLoggingCustomization($value)
    {
        $parsedData = static::parseYamlData($value);

        if (is_array($parsedData)) {
            SimpleStorageHelper::set(static::LOGGING_CUSTOMIZATION, $value);
        }

        if (static::getLoggingCustomizationDefault() === $parsedData) {
            SimpleStorageHelper::drop(static::LOGGING_CUSTOMIZATION);
        } else {
            Bootstrap::getLogger()->warning(
                vsprintf(
                    'Got unexpected logging customization data \'%s\' converted from \'%s\'',
                    [$parsedData, $value]
                )
            );
        }
    }

    /**
     * UI settings Table View Page Size, default = 20 (configured via yaml file)
     */
    const SMARTLING_CUSTOM_PAGE_SIZE = 'smartling_ui_page_size';

    public static function getPageSizeDefault()
    {
        return Bootstrap::getContainer()->getParameter('submission.pagesize.default');
    }

    public static function getPageSize()
    {
        return SimpleStorageHelper::get(static::SMARTLING_CUSTOM_PAGE_SIZE, static::getPageSizeDefault());
    }

    public static function setPageSize($value)
    {
        SimpleStorageHelper::set(static::SMARTLING_CUSTOM_PAGE_SIZE, $value);

        if (static::getPageSizeDefault() === (int)$value) {
            SimpleStorageHelper::drop(static::SMARTLING_CUSTOM_PAGE_SIZE);
        }
    }

    public static function getPageSizeRuntime()
    {
        return Bootstrap::getContainer()->getParameter('submission.pagesize');
    }
}