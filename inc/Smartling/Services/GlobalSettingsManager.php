<?php

namespace Smartling\Services;

use Smartling\Bootstrap;
use Smartling\Helpers\SimpleStorageHelper;
use Smartling\Vendor\Symfony\Component\Yaml\Parser;

/**
 * @see js/configuration-profile-form.js change frontend handling there as well when changing options here
 */
class GlobalSettingsManager
{
    /**
     * Disable on-boot self-diagnostics (not recommended)
     */
    private const SELF_CHECK_IDENTIFIER = 'smartling_static_check_disabled';
    private const SELF_CHECK_IDENTIFIER_DEFAULT = 0;

    public static function getSkipSelfCheck()
    {
        return SimpleStorageHelper::get(static::SELF_CHECK_IDENTIFIER, self::SELF_CHECK_IDENTIFIER_DEFAULT);
    }

    public static function setSkipSelfCheck($value): void
    {
        SimpleStorageHelper::set(static::SELF_CHECK_IDENTIFIER, $value);

        if (self::SELF_CHECK_IDENTIFIER_DEFAULT === (int)$value) {
            SimpleStorageHelper::drop(static::SELF_CHECK_IDENTIFIER);
        }
    }

    /**
     * Disable logging (not recommended)
     */
    private const DISABLE_LOGGING = 'smartling_disable_logging';
    private const DISABLE_LOGGING_DEFAULT = 0;

    public static function getDisableLogging()
    {
        return SimpleStorageHelper::get(static::DISABLE_LOGGING, self::DISABLE_LOGGING_DEFAULT);
    }

    public static function setDisableLogging($value): void
    {
        SimpleStorageHelper::set(static::DISABLE_LOGGING, $value);

        if (self::DISABLE_LOGGING_DEFAULT === (int)$value) {
            SimpleStorageHelper::drop(static::DISABLE_LOGGING);
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

    public static function getLogFileSpec(): string
    {
        return SimpleStorageHelper::get(static::SMARTLING_CUSTOM_LOG_FILE, static::getLogFileSpecDefault());
    }

    public static function setLogFileSpec(string $value): void
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

    public const SMARTLING_FRONTEND_GENERATE_LOCK_IDS = 'smartling_frontend_generate_lock_ids';
    public const SMARTLING_GENERATE_LOCK_IDS_DEFAULT = "0";

    public static function isGenerateLockIdsEnabled(): bool
    {
        return SimpleStorageHelper::get(static::SMARTLING_FRONTEND_GENERATE_LOCK_IDS, static::SMARTLING_GENERATE_LOCK_IDS_DEFAULT) === "1";
    }

    public static function setGenerateLockIdsFrontend(string $value): void
    {
        if ($value === static::SMARTLING_GENERATE_LOCK_IDS_DEFAULT) {
            SimpleStorageHelper::drop(static::SMARTLING_FRONTEND_GENERATE_LOCK_IDS);
        } else {
            SimpleStorageHelper::set(static::SMARTLING_FRONTEND_GENERATE_LOCK_IDS, $value);
        }
    }

    public const SMARTLING_RELATED_CONTENT_SELECT_STATE = 'smartling_related_content_select_state';
    public const SMARTLING_RELATED_CHECKBOX_STATE_DEFAULT = 0;

    public static function getRelatedContentSelectState(): int
    {
        return (int)SimpleStorageHelper::get(
            self::SMARTLING_RELATED_CONTENT_SELECT_STATE,
            self::SMARTLING_RELATED_CHECKBOX_STATE_DEFAULT,
        );
    }

    public static function setRelatedContentSelectState(int $value): void
    {
        SimpleStorageHelper::set(static::SMARTLING_RELATED_CONTENT_SELECT_STATE, $value);

        if (self::SMARTLING_RELATED_CHECKBOX_STATE_DEFAULT === $value) {
            SimpleStorageHelper::drop(static::SMARTLING_RELATED_CONTENT_SELECT_STATE);
        }
    }

    private const SMARTLING_FILTER_UI_VISIBLE = 'smartling_filter_ui_visible';
    private const SMARTLING_FILTER_UI_VISIBLE_DEFAULT = 0;

    public static function getFilterUiVisible(): int
    {
        return SimpleStorageHelper::get(static::SMARTLING_FILTER_UI_VISIBLE, static::SMARTLING_FILTER_UI_VISIBLE_DEFAULT);
    }

    public static function setFilterUiVisible(int $value): void
    {
        SimpleStorageHelper::set(static::SMARTLING_FILTER_UI_VISIBLE, $value);

        if (static::SMARTLING_FILTER_UI_VISIBLE_DEFAULT === $value) {
            SimpleStorageHelper::drop(static::SMARTLING_FILTER_UI_VISIBLE);
        }
    }

    public const SETTING_REMOVE_ACF_PARSE_SAVE_BLOCKS_FILTER = 'smartling_remove_acf_parse_save_blocks_filter';
    public const SETTING_REMOVE_ACF_PARSE_SAVE_BLOCKS_FILTER_DEFAULT = "1";

    public static function isRemoveAcfParseSaveBlocksFilter(): bool
    {
        return SimpleStorageHelper::get(self::SETTING_REMOVE_ACF_PARSE_SAVE_BLOCKS_FILTER, self::SETTING_REMOVE_ACF_PARSE_SAVE_BLOCKS_FILTER_DEFAULT) === "1";
    }

    public static function setRemoveAcfParseSaveBlocksFilter(string $value): void
    {
        if ($value === self::SETTING_REMOVE_ACF_PARSE_SAVE_BLOCKS_FILTER_DEFAULT) {
            SimpleStorageHelper::drop(self::SETTING_REMOVE_ACF_PARSE_SAVE_BLOCKS_FILTER);
        } else {
            SimpleStorageHelper::set(self::SETTING_REMOVE_ACF_PARSE_SAVE_BLOCKS_FILTER, $value);
        }
    }

    public const SETTING_ADD_SLASHES_BEFORE_SAVING_CONTENT = 'smartling_add_slashes_before_saving_content';
    public const SETTING_ADD_SLASHES_BEFORE_SAVING_CONTENT_DEFAULT = "1";

    public static function isAddSlashesBeforeSavingPostContent(): bool
    {
        return SimpleStorageHelper::get(self::SETTING_ADD_SLASHES_BEFORE_SAVING_CONTENT, self::SETTING_ADD_SLASHES_BEFORE_SAVING_CONTENT_DEFAULT) === "1";
    }

    public static function setAddSlashesBeforeSavingContent(string $value): void
    {
        if ($value === self::SETTING_ADD_SLASHES_BEFORE_SAVING_CONTENT_DEFAULT) {
            SimpleStorageHelper::drop(self::SETTING_ADD_SLASHES_BEFORE_SAVING_CONTENT);
        } else {
            SimpleStorageHelper::set(self::SETTING_ADD_SLASHES_BEFORE_SAVING_CONTENT, $value);
        }
    }

    public const SETTING_ADD_SLASHES_BEFORE_SAVING_META = 'smartling_add_slashes_before_saving_meta';
    public const SETTING_ADD_SLASHES_BEFORE_SAVING_META_DEFAULT = "1";

    public static function isAddSlashesBeforeSavingPostMeta(): bool
    {
        return SimpleStorageHelper::get(self::SETTING_ADD_SLASHES_BEFORE_SAVING_META, self::SETTING_ADD_SLASHES_BEFORE_SAVING_META_DEFAULT) === "1";
    }

    public static function setAddSlashesBeforeSavingMeta(string $value): void
    {
        if ($value === self::SETTING_ADD_SLASHES_BEFORE_SAVING_META_DEFAULT) {
            SimpleStorageHelper::drop(self::SETTING_ADD_SLASHES_BEFORE_SAVING_META);
        } else {
            SimpleStorageHelper::set(self::SETTING_ADD_SLASHES_BEFORE_SAVING_META, $value);
        }
    }

    public const SETTING_CUSTOM_DIRECTIVES = 'smartling_custom_directives';
    public const SETTING_CUSTOM_DIRECTIVES_DEFAULT = '';

    public static function getCustomDirectives(): string
    {
        return SimpleStorageHelper::get(self::SETTING_CUSTOM_DIRECTIVES, self::SETTING_CUSTOM_DIRECTIVES_DEFAULT);
    }

    public static function setCustomDirectives(string $value): void
    {
        if ($value === self::SETTING_CUSTOM_DIRECTIVES_DEFAULT) {
            SimpleStorageHelper::drop(self::SETTING_CUSTOM_DIRECTIVES);
        } else {
            SimpleStorageHelper::set(self::SETTING_CUSTOM_DIRECTIVES, $value);
        }
    }
}
