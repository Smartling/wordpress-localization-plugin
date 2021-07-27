<?php

namespace Smartling\Services;

use Smartling\Bootstrap;
use Smartling\Helpers\SimpleStorageHelper;
use Symfony\Component\Yaml\Parser;

class GlobalSettingsManager
{
    /**
     * Disable on-boot self-diagnostics (not recommended)
     */
    private const SELF_CHECK_IDENTIFIER = 'smartling_static_check_disabled';
    /**
     * Last state of include related items checkbox in post edit widget
     */
    public const RELATED_CHECKBOX_STATE = 'related_checkbox_state';

    public static function getSkipSelfCheckDefault(): int
    {
        return 0;
    }

    public static function getSkipSelfCheck()
    {
        return SimpleStorageHelper::get(static::SELF_CHECK_IDENTIFIER, static::getSkipSelfCheckDefault());
    }

    public static function setSkipSelfCheck($value): void
    {
        SimpleStorageHelper::set(static::SELF_CHECK_IDENTIFIER, $value);

        if (static::getSkipSelfCheckDefault() === (int)$value) {
            SimpleStorageHelper::drop(static::SELF_CHECK_IDENTIFIER);
        }
    }

    /**
     * Disable logging (not recommended)
     */
    private const DISABLE_LOGGING = 'smartling_disable_logging';

    public static function getDisableLoggingDefault(): int
    {
        return 0;
    }

    public static function getDisableLogging()
    {
        return SimpleStorageHelper::get(static::DISABLE_LOGGING, static::getDisableLoggingDefault());
    }

    public static function setDisableLogging($value): void
    {
        SimpleStorageHelper::set(static::DISABLE_LOGGING, $value);

        if (static::getDisableLoggingDefault() === (int)$value) {
            SimpleStorageHelper::drop(static::DISABLE_LOGGING);
        }
    }

    /**
     * Disable built-in ACF-Pro plugin support
     */
    private const DISABLE_ACF = 'smartling_disable_acf';

    public static function getDisableACFDefault(): int
    {
        return 0;
    }

    public static function getDisableACF()
    {
        return SimpleStorageHelper::get(static::DISABLE_ACF, static::getDisableACFDefault());
    }

    public static function setDisableACF($value): void
    {
        SimpleStorageHelper::set(static::DISABLE_ACF, $value);

        if (static::getDisableACFDefault() === (int)$value) {
            SimpleStorageHelper::drop(static::DISABLE_ACF);
        }
    }

    public static function isAcfDisabled(): bool
    {
        return 1 === (int)self::getDisableACF();
    }

    /**
     * Disable built-in ACF-Pro Database definitions lookup support (not recommended)
     */
    private const DISABLE_ACF_DB_LOOKUP = 'smartling_disable_db_lookup';

    public static function getDisableAcfDbLookupDefault(): int
    {
        return 0;
    }

    public static function getDisableAcfDbLookup()
    {
        return SimpleStorageHelper::get(static::DISABLE_ACF_DB_LOOKUP, static::getDisableAcfDbLookupDefault());
    }

    public static function setDisableAcfDbLookup($value): void
    {
        SimpleStorageHelper::set(static::DISABLE_ACF_DB_LOOKUP, $value);

        if (static::getDisableAcfDbLookupDefault() === (int)$value) {
            SimpleStorageHelper::drop(static::DISABLE_ACF_DB_LOOKUP);
        }
    }

    /**
     * Log file name customization
     */
    private const SMARTLING_CUSTOM_LOG_FILE = 'smartling_log_file';

    public static function getLogFileSpecDefault(): string
    {
        return Bootstrap::getLogFileName(false, true);
    }

    public static function getLogFileSpec()
    {
        return SimpleStorageHelper::get(static::SMARTLING_CUSTOM_LOG_FILE, static::getLogFileSpecDefault());
    }

    public static function setLogFileSpec($value): void
    {
        SimpleStorageHelper::set(static::SMARTLING_CUSTOM_LOG_FILE, $value);

        if (static::getLogFileSpecDefault() === $value) {
            SimpleStorageHelper::drop(static::SMARTLING_CUSTOM_LOG_FILE);
        }
    }

    /**
     * Logging configuration customization
     */
    private const LOGGING_CUSTOMIZATION = 'smartling_logging_customization';

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

    public static function setLoggingCustomization($value): void
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
    private const SMARTLING_CUSTOM_PAGE_SIZE = 'smartling_ui_page_size';

    public static function getPageSizeDefault()
    {
        return Bootstrap::getContainer()->getParameter('submission.pagesize.default');
    }

    public static function getPageSize()
    {
        return SimpleStorageHelper::get(static::SMARTLING_CUSTOM_PAGE_SIZE, static::getPageSizeDefault());
    }

    public static function setPageSize($value): void
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

    private const SMARTLING_HANDLE_RELATIONS_MANUALLY = 'smartling_handle_relations_manually';

    public static function getHandleRelationsManuallyDefault(): int
    {
        return 1;
    }

    public static function getHandleRelationsManually()
    {
        return SimpleStorageHelper::get(static::SMARTLING_HANDLE_RELATIONS_MANUALLY, static::getHandleRelationsManuallyDefault());
    }

    public static function isHandleRelationsManually(): bool
    {
        return 1 === (int)self::getHandleRelationsManually();
    }

    public static function setHandleRelationsManually(int $value): void
    {
        SimpleStorageHelper::set(static::SMARTLING_HANDLE_RELATIONS_MANUALLY, $value);

        if (static::getHandleRelationsManuallyDefault() === (int)$value) {
            SimpleStorageHelper::drop(static::SMARTLING_HANDLE_RELATIONS_MANUALLY);
        }
    }

    public const SMARTLING_FRONTEND_GENERATE_LOCK_IDS = 'smartling_frontend_generate_lock_ids';

    public static function isGenerateLockIdsFrontendDefault(): bool
    {
        return false;
    }

    public static function isGenerateLockIdsEnabled(): bool
    {
        return SimpleStorageHelper::get(static::SMARTLING_FRONTEND_GENERATE_LOCK_IDS, static::isGenerateLockIdsFrontendDefault()) === "1";
    }

    public static function setGenerateLockIdsFrontend(bool $value): void
    {
        if ($value === static::isGenerateLockIdsFrontendDefault()) {
            SimpleStorageHelper::drop(static::SMARTLING_FRONTEND_GENERATE_LOCK_IDS);
        } else {
            SimpleStorageHelper::set(static::SMARTLING_FRONTEND_GENERATE_LOCK_IDS, $value);
        }
    }

    private const SMARTLING_RELATED_CHECKBOX_STATE = 'smartling_related_checkbox_state';

    public static function getRelatedContentCheckboxDefault(): int
    {
        return 1;
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

    public static function isRelatedContentCheckboxChecked(): bool
    {
        return 1 === (int)self::getRelatedContentCheckboxState();
    }

    public static function setRelatedContentCheckboxState(int $value): void
    {
        SimpleStorageHelper::set(static::SMARTLING_RELATED_CHECKBOX_STATE, $value);

        if (static::getRelatedContentCheckboxDefault() === (int)$value) {
            SimpleStorageHelper::drop(static::SMARTLING_RELATED_CHECKBOX_STATE);
        }
    }

    private const SMARTLING_FILTER_UI_VISIBLE = 'smartling_filter_ui_visible';

    public static function getFilterUiVisible()
    {
        return SimpleStorageHelper::get(static::SMARTLING_FILTER_UI_VISIBLE, 0);
    }

    public static function setFilterUiVisible($value): void
    {
        SimpleStorageHelper::set(static::SMARTLING_FILTER_UI_VISIBLE, $value);

        if (0 === (int)$value) {SimpleStorageHelper::drop(static::SMARTLING_FILTER_UI_VISIBLE);
        }
    }
}
