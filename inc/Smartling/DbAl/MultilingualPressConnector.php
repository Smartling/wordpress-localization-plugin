<?php

namespace Smartling\DbAl;

use Mlp_Content_Relations;
use Mlp_Content_Relations_Interface;
use Mlp_Site_Relations;
use Mlp_Site_Relations_Interface;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\QueryBuilder\Condition\Condition;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBlock;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBuilder;
use Smartling\Helpers\QueryBuilder\QueryBuilder;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Submissions\SubmissionEntity;
use wpdb;

class MultilingualPressConnector extends LocalizationPluginAbstract
{
    const MULTILINGUAL_PRESS_PRO_SITE_OPTION_KEY_NAME = 'inpsyde_multilingual';

    const ML_SITE_LINK_TABLE_NAME = 'mlp_site_relations';

    const ML_CONTENT_LINK_TABLE_NAME = 'multilingual_linked';
    const UNKNOWN_LOCALE = '';

    /**
     * @var wpdb
     */
    private $wpdb;

    /**
     * @var array
     */
    protected static $_blogLocalesCache = [];

    /**
     * @throws \Exception
     */
    private function cacheLocales()
    {
        if (empty(static::$_blogLocalesCache)) {
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
                    static::$_blogLocalesCache[$blogId] = $temp;
                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function getLocales()
    {
        if (!function_exists('get_site_option')) {
            $this->directRunFallback('Direct run detected. Required run as Wordpress plugin.');
        }
        $this->cacheLocales();

        $locales = [];
        foreach (static::$_blogLocalesCache as $blogId => $blogLocale) {
            $locales[$blogId] = $blogLocale['text'];
        }

        return $locales;
    }

    /**
     * @inheritdoc
     */
    public function getBlogLocaleById($blogId)
    {
        if (!function_exists('get_site_option')) {
            $this->directRunFallback('Direct run detected. Required run as Wordpress plugin.');
        }

        $this->cacheLocales();

        if (array_key_exists($blogId, static::$_blogLocalesCache)) {
            $locale = static::$_blogLocalesCache[$blogId];
        } else {
            $locale = ['lang' => self::UNKNOWN_LOCALE];
        }

        return $locale['lang'];
    }

    /**
     * @inheritdoc
     */
    public function __construct(SiteHelper $helper, array $ml_plugin_statuses)
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        parent::__construct($helper, $ml_plugin_statuses);
    }

    /**
     * @return Mlp_Site_Relations_Interface|null
     */
    private function initSiteRelationsSubsystem()
    {
        if ($this->isMultilingualPluginActive()) {
            try {
                return new Mlp_Site_Relations($this->wpdb, self::ML_SITE_LINK_TABLE_NAME);
            } catch (\Exception $e) {
                $this->getLogger()->debug('Failed to init site relations subsystem: ' . $e->getMessage());
                return null;
            }
        }
        return null;
    }

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

    /**
     * @return Mlp_Content_Relations|null
     */
    private function initContentRelationSubsystem()
    {
        if ($this->isMultilingualPluginActive()) {
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
     * @inheritdoc
     */
    public function getLinkedBlogIdsByBlogId($blogId)
    {
        $relations = $this->initSiteRelationsSubsystem();

        try {
            $relatedSites = $relations === null ? [] : $relations->get_related_sites($blogId);
        } catch (\Exception $e) {
            $this->getLogger()->debug('Failed to get related sites: ' . $e->getMessage());
            $relatedSites = [];
        }

        $result = [];

        foreach ($relatedSites as $site) {
            $result[] = (int)$site;
        }

        return $result;
    }

    /**
     * Converts representation of wordpress content types to multilingual content types
     *
     * @param string $contentType
     *
     * @return string
     */
    private function convertType($contentType)
    {
        $contentType = $contentType === 'page'
            ? 'post'
            : $contentType;

        $contentType = in_array($contentType, WordpressContentTypeHelper::getSupportedTaxonomyTypes(), true)
            ? 'term'
            : $contentType;

        return $contentType;
    }

    /**
     * @inheritdoc
     */
    public function getLinkedObjects($sourceBlogId, $sourceContentId, $contentType)
    {
        $relations = $this->initContentRelationSubsystem();

        try {
            return $relations === null ? [] : $relations->get_relations($sourceBlogId, $sourceContentId, $this->convertType($contentType));
        } catch (\Exception $e) {
            $this->getLogger()->debug('Failed to get relations: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @inheritdoc
     */
    public function linkObjects(SubmissionEntity $submission)
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

    /**
     * @inheritdoc
     */
    public function unlinkObjects(SubmissionEntity $submission)
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

    private function isMultilingualPluginActive()
    {
        $expectedClasses = ['Mlp_Helpers', 'Mlp_Content_Relations', 'Mlp_Site_Relations'];
        foreach ($expectedClasses as $class) {
            if (!class_exists($class)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param int $blogId
     *
     * @return string
     */
    public function getBlogLanguageById($blogId)
    {
        $result = '';
        if ($this->isMultilingualPluginActive()) {
            $result = \Mlp_Helpers::get_blog_language($blogId);
        } else {
            $message = 'Seems like Multilingual Press plugin is not installed and/or activated. Cannot read blog locale.';
            $this->getLogger()->debug($message);
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function getBlogNameByLocale($locale)
    {
        $tableName = 'mlp_languages';
        $condition = ConditionBlock::getConditionBlock();
        $condition->addCondition(
            Condition::getCondition(
                ConditionBuilder::CONDITION_SIGN_EQ,
                'wp_locale',
                [
                    $locale,
                ]
            )
        );
        $query = QueryBuilder::buildSelectQuery(
            $this->wpdb->base_prefix . $tableName,
            [
                'english_name',
            ],
            $condition,
            [],
            [
                'page'  => 1,
                'limit' => 1,
            ]
        );
        $r = $this->wpdb->get_results($query, ARRAY_A);

        return 1 === count($r) ? $r[0]['english_name'] : $locale;
    }
}
