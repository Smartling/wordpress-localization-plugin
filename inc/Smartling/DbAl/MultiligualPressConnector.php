<?php

namespace Smartling\DbAl;

use Mlp_Content_Relations;
use Mlp_Content_Relations_Interface;
use Mlp_Site_Relations;
use Mlp_Site_Relations_Interface;
use Psr\Log\LoggerInterface;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\QueryBuilder\Condition\Condition;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBlock;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBuilder;
use Smartling\Helpers\QueryBuilder\QueryBuilder;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Submissions\SubmissionEntity;
use wpdb;

/**
 * Class MultiligualPressConnector
 * @package Smartling\DbAl
 */
class MultiligualPressConnector extends LocalizationPluginAbstract
{

    /**
     * option key name
     */
    const MULTILINGUAL_PRESS_PRO_SITE_OPTION = 'inpsyde_multilingual';

    /**
     * table name
     */
    const ML_SITE_LINK_TABLE = 'mlp_site_relations';

    /**
     * table name
     */
    const ML_CONTENT_LINK_TABLE = 'multilingual_linked';

    /**
     * @var wpdb
     */
    private $wpdb;

    /**
     * @return wpdb
     */
    public function getWpdb()
    {
        return $this->wpdb;
    }

    /**
     * @var array
     */
    protected static $_blogLocalesCache = [];

    /**
     * @throws \Exception
     */
    private function cacheLocales()
    {
        if (empty(self::$_blogLocalesCache)) {
            $rawValue = get_site_option(self::MULTILINGUAL_PRESS_PRO_SITE_OPTION, false, false);

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
                        $temp['lang'] = 'unknown';
                    }
                    self::$_blogLocalesCache[$blogId] = $temp;
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
        foreach (self::$_blogLocalesCache as $blogId => $blogLocale) {
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

        if (array_key_exists($blogId, self::$_blogLocalesCache)) {
            $locale = self::$_blogLocalesCache[$blogId];
        } else {
            //$message = vsprintf('The blog %s is not configured in multilingual press plugin', [$blogId]);
            // $this->getLogger()->warning($message);
            $locale = ['lang' => 'unknown'];
        }

        return $locale['lang'];
    }


    /**
     * @inheritdoc
     */
    public function __construct(LoggerInterface $logger, SiteHelper $helper, array $ml_plugin_statuses)
    {
        global $wpdb;

        $this->wpdb = $wpdb;

        parent::__construct($logger, $helper, $ml_plugin_statuses);

        if (false === $ml_plugin_statuses['multilingual-press-pro'] && defined('SMARTLING_CLI_EXECUTION') &&
            SMARTLING_CLI_EXECUTION === false
        ) {
            //throw new \Exception( 'Active plugin not found Exception' );
        }
    }

    /**
     * @return Mlp_Site_Relations_Interface
     */
    private function initiSiteRelationsSubsystem()
    {
        return new Mlp_Site_Relations($this->getWpdb(), self::ML_SITE_LINK_TABLE);
    }

    private function getContentLinkTable()
    {

        $tableName = $this->getWpdb()->base_prefix . self::ML_CONTENT_LINK_TABLE;

        if (class_exists('\Mlp_Db_Table_Name')) {
            /**
             * New version of MLP (WP 4.3+)
             */
            return new \Mlp_Db_Table_Name(
                $tableName,
                new \Mlp_Db_Table_List(
                    $this->getWpdb()
                )
            );
        } else {
            return $tableName;
        }
    }

    /**
     * @return Mlp_Content_Relations_Interface
     */
    private function initContentRelationSubsystem()
    {
        return new Mlp_Content_Relations(
            $this->getWpdb(),
            $this->initiSiteRelationsSubsystem(),
            $this->getContentLinkTable()
        );
    }

    /**
     * @inheritdoc
     */
    public function getLinkedBlogIdsByBlogId($blogId)
    {

        $relations = $this->initiSiteRelationsSubsystem();

        $res = $relations->get_related_sites($blogId);

        $result = [];

        foreach ($res as $site) {
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

        $contentType = in_array($contentType,
                                WordpressContentTypeHelper::getSupportedTaxonomyTypes())
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

        return $relations->get_relations($sourceBlogId, $sourceContentId, $this->convertType($contentType));
    }

    /**
     * @inheritdoc
     */
    public function linkObjects(SubmissionEntity $submission)
    {
        $relations = $this->initContentRelationSubsystem();

        $contentType = $submission->getContentType();

        return $relations->set_relation(
            $submission->getSourceBlogId(),
            $submission->getTargetBlogId(),
            $submission->getSourceId(),
            $submission->getTargetId(),
            $this->convertType($contentType));
    }

    /**
     * @inheritdoc
     */
    public function unlinkObjects(SubmissionEntity $submission)
    {
        $relations = $this->initContentRelationSubsystem();

        return $relations->delete_relation(
            $submission->getSourceBlogId(),
            $submission->getTargetBlogId(),
            $submission->getSourceId(),
            $submission->getTargetId(),
            $this->convertType($submission->getContentType())
        );
    }

    private function isMultilingualPluginActive()
    {
        return class_exists('Mlp_Helpers');
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
            $this->getLogger()
                ->warning($message);
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function getBlogNameByLocale($locale)
    {
        /**
         * @var \wpdb $wpdb
         */
        global $wpdb;

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
            $wpdb->base_prefix . $tableName,
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
        $r = $wpdb->get_results($query, ARRAY_A);
        $result = 1 === count($r) ? $r[0]['english_name'] : $locale;

        return $result;
    }
}