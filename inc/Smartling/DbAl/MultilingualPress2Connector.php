<?php

namespace Smartling\DbAl;

use Mlp_Content_Relations;
use Mlp_Site_Relations;
use Mlp_Site_Relations_Interface;
use Smartling\Exception\SmartlingDirectRunRuntimeException;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\SimpleStorageHelper;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Submissions\SubmissionEntity;
use wpdb;

class MultilingualPress2Connector extends MultilingualPressConnector implements LocalizationPluginProxyInterface
{
    use LoggerSafeTrait;

    public const MULTILINGUAL_PRESS_PRO_SITE_OPTION_KEY_NAME = 'inpsyde_multilingual';

    private const ML_SITE_LINK_TABLE_NAME = 'mlp_site_relations';
    private const ML_CONTENT_LINK_TABLE_NAME = 'multilingual_linked';
    private const UNKNOWN_LOCALE = '';

    /**
     * @var wpdb
     */
    private $wpdb;

    private array $blogLocalesCache = [];

    /**
     * @param wpdb $wpdb
     * @noinspection PhpMissingParamTypeInspection wpdb used for testing
     */
    public function __construct($wpdb = null)
    {
        if ($wpdb === null) {
            global $wpdb;
        }
        $this->wpdb = $wpdb;
    }

    public function addHooks(): void
    {
        $data = SimpleStorageHelper::get('state_modules', false);
        $advTranslatorKey = 'class-Mlp_Advanced_Translator';
        if (is_array($data) && array_key_exists($advTranslatorKey, $data) && 'off' !== $data[$advTranslatorKey]) {
            $msg = '<strong>Advanced Translator</strong> feature of Multilingual Press plugin is currently turned on.' .
                '<br/>Please turn it off to use Smartling-connector plugin. <br/> Use <a href="' . get_site_url() .
                '/wp-admin/network/settings.php?page=mlp"><strong>this link</strong></a> to visit Multilingual Press ' .
                'network settings page.';
            $this->getLogger()->critical('Boot :: ' . $msg);
            DiagnosticsHelper::addDiagnosticsMessage($msg, true);
        }

        add_filter('wpmu_new_blog', function () {
            // ignore basedOn value by setting it to 0
            $_POST['blog']['basedon'] = 0;
        }, 9);

        add_filter('mlp_after_new_blog_fields', function () {
            // remove basedOn select element from UI
            echo '<script>(function($){$($(\'#mlp-base-site-id\').parents(\'tr\')).remove();})(jQuery);</script>';
        }, 99);
    }

    /**
     * @throws \Exception
     */
    private function cacheLocales(): void
    {
        if (!function_exists('get_site_option')) {
            throw new SmartlingDirectRunRuntimeException('Direct run detected. Required run as Wordpress plugin.');
        }
        if (empty($this->blogLocalesCache)) {
            $rawValue = get_site_option(self::MULTILINGUAL_PRESS_PRO_SITE_OPTION_KEY_NAME, false, false);

            if (false === $rawValue) {
                $message = vsprintf('Locales and/or Links are not set with multilingual press plugin.', []);
                $this->getLogger()->critical('SettingsPage:Render ' . $message);
                DiagnosticsHelper::addDiagnosticsMessage($message, false);
            } else {
                foreach ($rawValue as $blogId => $item) {
                    $temp = [
                        'text' => $item['text'],
                    ];
                    if (array_key_exists('lang', $item)) {
                        $temp['lang'] = $item['lang'];
                    } else {
                        $temp['lang'] = self::UNKNOWN_LOCALE;
                    }
                    $this->blogLocalesCache[$blogId] = $temp;
                }
            }
        }
    }

    public function getBlogLocaleById(int $blogId): string
    {
        $this->cacheLocales();

        if (array_key_exists($blogId, $this->blogLocalesCache)) {
            $locale = $this->blogLocalesCache[$blogId];
        } else {
            $locale = ['lang' => self::UNKNOWN_LOCALE];
        }

        return $locale['lang'];
    }

    private function initSiteRelationsSubsystem(): ?Mlp_Site_Relations_Interface
    {
        if ($this->isActive()) {
            try {
                return new Mlp_Site_Relations($this->wpdb, self::ML_SITE_LINK_TABLE_NAME);
            } catch (\Exception $e) {
                $this->getLogger()->debug('Failed to init site relations subsystem: ' . $e->getMessage());
                return null;
            }
        }
        return null;
    }

    /**
     * @return \Mlp_Db_Table_Name|string
     */
    private function getContentLinkTable()
    {
        $tableName = $this->wpdb->base_prefix . self::ML_CONTENT_LINK_TABLE_NAME;

        if (class_exists('\Mlp_Db_Table_Name')) {
            /**
             * New version of MLP (WP 4.3+)
             */
            return new \Mlp_Db_Table_Name(
                $tableName,
                new \Mlp_Db_Table_List(
                    $this->wpdb
                )
            );
        }

        return $tableName;
    }

    private function initContentRelationSubsystem(): ?Mlp_Content_Relations
    {
        if ($this->isActive()) {
            try {
                return new Mlp_Content_Relations(
                    $this->wpdb,
                    $this->initSiteRelationsSubsystem(),
                    $this->getContentLinkTable()
                );
            } catch (\Exception $e) {
                $this->getLogger()->debug('Failed to init content relations subsystem: ' . $e->getMessage());
                return null;
            }
        }
        return null;
    }

    /**
     * Converts representation of wordpress content types to multilingual content types
     *
     * @param string $contentType
     *
     * @return string
     */
    private function convertType(string $contentType): string
    {
        $contentType = $contentType === 'page'
            ? 'post'
            : $contentType;

        $contentType = in_array($contentType, WordpressContentTypeHelper::getSupportedTaxonomyTypes(), true)
            ? 'term'
            : $contentType;

        return $contentType;
    }

    public function linkObjects(SubmissionEntity $submission): bool
    {
        $relations = $this->initContentRelationSubsystem();

        $contentType = $submission->getContentType();

        try {
            return $relations === null ? false : $relations->set_relation(
                $submission->getSourceBlogId(),
                $submission->getTargetBlogId(),
                $submission->getSourceId(),
                $submission->getTargetId(),
                $this->convertType($contentType));
        } catch (\Exception $e) {
            $this->getLogger()->debug('Failed to link objects: ' . $e->getMessage());
            return false;
        }
    }

    public function unlinkObjects(SubmissionEntity $submission): bool
    {
        $relations = $this->initContentRelationSubsystem();

        try {
            return $relations === null ? false : $relations->delete_relation(
                $submission->getSourceBlogId(),
                $submission->getTargetBlogId(),
                $submission->getSourceId(),
                $submission->getTargetId(),
                $this->convertType($submission->getContentType())
            );
        } catch (\Exception $e) {
            $this->getLogger()->debug('Failed to unlink objects: ' . $e->getMessage());
            return false;
        }
    }

    public function isActive(): bool
    {
        $expectedClasses = ['Mlp_Helpers', 'Mlp_Content_Relations', 'Mlp_Site_Relations'];
        foreach ($expectedClasses as $class) {
            if (!class_exists($class)) {
                return false;
            }
        }
        return true;
    }

    public function getBlogNameByLocale(string $locale): string
    {
        return $this->getEnglishNameFromMlpLanguagesTable($locale, 'wp_locale', $this->wpdb);
    }
}
