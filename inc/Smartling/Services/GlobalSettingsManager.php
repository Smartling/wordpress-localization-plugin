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
    const RELATED_CHECKBOX_STATE = 'related_checkbox_state';
    const TAXONOMY_SOURCE = 'taxonomy_source';

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
        return Bootstrap::getLogFileName(false, true);
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
            SimpleStorageHelper::set(static::LOGGING_CUSTOMIZATION, $parsedData);
        } else {
            Bootstrap::getLogger()->warning(
                vsprintf(
                    'Got unexpected logging customization data \'%s\' converted from \'%s\'',
                    [var_export($parsedData, true), $value]
                )
            );
        }

        if (static::getLoggingCustomizationDefault() === $parsedData) {
            SimpleStorageHelper::drop(static::LOGGING_CUSTOMIZATION);
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

    const SMARTLING_HANDLE_RELATIONS_MANUALLY = 'smartling_handle_relations_manually';
    const SMARTLING_HANDLE_RELATIONS_MANUALLY_DEFAULT = 1;

    public static function getHandleRelationsManuallyDefault()
    {
        return static::SMARTLING_HANDLE_RELATIONS_MANUALLY_DEFAULT;
    }

    public static function getHandleRelationsManually()
    {
        return SimpleStorageHelper::get(static::SMARTLING_HANDLE_RELATIONS_MANUALLY, static::getHandleRelationsManuallyDefault());
    }

    /**
     * @return boolean
     */
    public static function isHandleRelationsManually()
    {
        return 1 === (int)self::getHandleRelationsManually();
    }

    public static function setHandleRelationsManually($value)
    {
        SimpleStorageHelper::set(static::SMARTLING_HANDLE_RELATIONS_MANUALLY, $value);

        if (static::getHandleRelationsManuallyDefault() === (int)$value) {
            SimpleStorageHelper::drop(static::SMARTLING_HANDLE_RELATIONS_MANUALLY);
        }
    }

    const SMARTLING_RELATED_CHECKBOX_STATE = 'smartling_related_checkbox_state';
    const SMARTLING_RELATED_CHECKBOX_STATE_DEFAULT = 1;

    /**
     * @return int
     */
    public static function getRelatedContentCheckboxDefault()
    {
        return static::SMARTLING_RELATED_CHECKBOX_STATE_DEFAULT;
    }

    /**
     * @return mixed
     */
    public static function getRelatedContentCheckboxState()
    {
        return SimpleStorageHelper::get(
            static::SMARTLING_RELATED_CHECKBOX_STATE,
            static::getRelatedContentCheckboxDefault()
        );
    }

    /**
     * @return boolean
     */
    public static function isRelatedContentCheckboxChecked()
    {
        return 1 === (int)self::getRelatedContentCheckboxState();
    }

    /**
     * @param int $value
     */
    public static function setRelatedContentCheckboxState($value)
    {
        SimpleStorageHelper::set(static::SMARTLING_RELATED_CHECKBOX_STATE, $value);

        if (static::getRelatedContentCheckboxDefault() === (int)$value) {
            SimpleStorageHelper::drop(static::SMARTLING_RELATED_CHECKBOX_STATE);
        }
    }

    const TAXONOMY_SOURCE_STATE = 'taxonomy_source_state';
    const TAXONOMY_SOURCE_DEFAULT = 0;

    /**
     * @return int
     */
    public static function getTaxonomySourceDefault()
    {
        return static::TAXONOMY_SOURCE_DEFAULT;
    }

    /**
     * @return int
     */
    public static function getTaxonomySourceState()
    {
        return (int)SimpleStorageHelper::get(
            static::SMARTLING_RELATED_CHECKBOX_STATE,
            static::getRelatedContentCheckboxDefault()
        );
    }

    /**
     * @return boolean
     */
    public static function isLinkTaxonomySource()
    {
        return 1 === (int)self::getTaxonomySourceState();
    }

    /**
     * @param int $value
     */
    public static function setTaxonomySourceState($value)
    {
        SimpleStorageHelper::set(static::SMARTLING_RELATED_CHECKBOX_STATE, $value);

        if (static::getRelatedContentCheckboxDefault() === (int)$value) {
            SimpleStorageHelper::drop(static::SMARTLING_RELATED_CHECKBOX_STATE);
        }
    }

    const SMARTLING_FILTER_UI_VISIBLE         = 'smartling_filter_ui_visible';
    const SMARTLING_FILTER_UI_VISIBLE_DEFAULT = 0;

    public static function getFilterUiVisible()
    {
        return SimpleStorageHelper::get(static::SMARTLING_FILTER_UI_VISIBLE, static::SMARTLING_FILTER_UI_VISIBLE_DEFAULT);
    }

    public static function setFilterUiVisible($value)
    {
        SimpleStorageHelper::set(static::SMARTLING_FILTER_UI_VISIBLE, $value);

        if (static::SMARTLING_FILTER_UI_VISIBLE_DEFAULT === (int)$value) {SimpleStorageHelper::drop(static::SMARTLING_FILTER_UI_VISIBLE);
        }
    }

}

