<?php

namespace Smartling\ContentTypes;

use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Helpers\PlaceholderHelper;
use Smartling\Helpers\PluginHelper;
use Smartling\Helpers\QueryBuilder\Condition\Condition;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBlock;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBuilder;
use Smartling\Helpers\QueryBuilder\QueryBuilder;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class ExternalContentAioseo extends ExternalContentAbstract
{
    private FieldsFilterHelper $fieldsFilterHelper;
    private PlaceholderHelper $placeholderHelper;
    private SiteHelper $siteHelper;
    private SmartlingToCMSDatabaseAccessWrapperInterface $db;
    private array $intFields = [
        'post_id',
        'og_image_width',
        'og_image_height',
        'twitter_use_og',
        'seo_score',
        'pillar_content',
        'robots_default',
        'robots_noindex',
        'robots_noarchive',
        'robots_nosnippet',
        'robots_nofollow',
        'robots_noimageindex',
        'robots_noodp',
        'robots_notranslate',
        'robots_max_snippet',
        'robots_max_videopreview',
        'limit_modified_date',
    ];
    private array $jsonFields = [
        'keywords',
        'keyphrases',
        'og_article_tags',
    ];
    private array $removeFields = [
        'created',
        'id',
        'page_analysis',
        'og_object_type',
        'og_image_type',
        'page_analysis',
        'twitter_card',
        'twitter_image_type',
        'schema_type',
        'schema_type_options',
        'robots_max_imagepreview',
        'tabs',
        'image_scan_date',
        'images',
        'priority',
        'frequency',
        'videos',
        'video_thumbnail',
    ];
    private array $tagFields = [
        'title',
        'description',
        'og_title',
        'og_description',
        'og_article_section',
    ];
    private ContentTypeHelper $contentTypeHelper;

    public function __construct(
        ContentTypeHelper $contentTypeHelper,
        FieldsFilterHelper $fieldsFilterHelper,
        PlaceholderHelper $placeholderHelper,
        PluginHelper $pluginHelper,
        SiteHelper $siteHelper,
        SmartlingToCMSDatabaseAccessWrapperInterface $db,
        SubmissionManager $submissionManager,
        WordpressFunctionProxyHelper $wpProxy
    )
    {
        parent::__construct($pluginHelper, $submissionManager, $wpProxy);
        $this->contentTypeHelper = $contentTypeHelper;
        $this->db = $db;
        $this->fieldsFilterHelper = $fieldsFilterHelper;
        $this->placeholderHelper = $placeholderHelper;
        $this->siteHelper = $siteHelper;
    }

    public function getSupportLevel(string $contentType, ?int $contentId = null): string
    {
        if ($this->contentTypeHelper->isPost($contentType)) {
            return parent::getSupportLevel($contentType, $contentId);
        }
        return self::NOT_SUPPORTED;
    }

    public function getContentFields(SubmissionEntity $submission, bool $raw): array
    {
        $fields = $this->siteHelper->withBlog($submission->getSourceBlogId(), function () use ($submission) {
            return $this->db->fetchPrepared('select * from ' . $this->db->getPrefix() . 'aioseo_posts where post_id = %d', $submission->getSourceId())[0] ?? [];
        });
        return $raw ? $this->transformContentRaw($fields) : $this->transformContentForUpload($fields);
    }

    public function getMaxVersion(): string
    {
        return '4.2';
    }

    public function getMinVersion(): string
    {
        return '4.2';
    }

    public function getPluginId(): string
    {
        return 'aioseo';
    }

    public function getPluginPaths(): array
    {
        return ['all-in-one-seo-pack/all_in_one_seo_pack.php'];
    }

    public function setContentFields(array $original, array $translation, SubmissionEntity $submission): ?array
    {
        $translation = $translation[$this->getPluginId()] ?? [];
        foreach ($this->jsonFields as $field) {
            if (array_key_exists($field, $translation)) {
                $translation[$field] = json_encode($translation[$field], JSON_THROW_ON_ERROR);
            }
        }
        foreach ($this->tagFields as $field) {
            if (array_key_exists($field, $translation)) {
                $translation[$field] = $this->placeholderHelper->removePlaceholders($translation[$field] ?? '');
            }
        }
        unset($translation['id']);
        $translation['post_id'] = $submission->getTargetId();
        $this->siteHelper->withBlog($submission->getTargetBlogId(), function () use ($translation, $submission) {
            if (($this->db->fetchPrepared("select count(*) c from {$this->db->getPrefix()}aioseo_posts where post_id = %d", $submission->getTargetId())[0]['c'] ?? null) === '0') {
                $this->db->query(QueryBuilder::buildInsertQuery($this->db->getPrefix() . 'aioseo_posts', $translation));
            } else {
                $conditionBlock = ConditionBlock::getConditionBlock();
                $conditionBlock->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, 'post_id', [$submission->getTargetId()]));
                $this->db->query(QueryBuilder::buildUpdateQuery($this->db->getPrefix() . 'aioseo_posts', $translation, $conditionBlock));
            }
        });

        return null;
    }

    public function addPlaceholders(?string $string): ?string
    {
        if ($string === null) {
            return null;
        }

        return preg_replace('~(#[a-z_]+)~i', $this->placeholderHelper->addPlaceholders('$1'), $string);
    }

    public function transformContentForUpload(array $content): array
    {
        foreach ($this->removeFields as $field) {
            unset($content[$field]);
        }
        foreach ($this->tagFields as $field) {
            $content[$field] = $this->addPlaceholders($content[$field]);
        }
        foreach ($this->jsonFields as $field) {
            try {
                $content[$field] = json_decode($content[$field], true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                $content[$field] = null;
            }
        }
        if (is_array($content['keyphrases']) && array_key_exists('focus', $content['keyphrases'])) {
            $content['keyphrases']['focus'] = ['keyphrase' => $content['keyphrases']['focus']['keyphrase']];
            unset($content['keyphrases']['additional']);
        }
        foreach ($this->jsonFields as $field) {
            if (is_array($content[$field])) {
                $content[$field] = $this->fieldsFilterHelper->flattenArray($content[$field]);
            }
        }
        foreach ($this->intFields as $field) {
            unset($content[$field]);
        }

        return $content;
    }

    public function transformContentRaw(array $content): array
    {
        foreach ($this->jsonFields as $field) {
            if (array_key_exists($field, $content)) {
                $content[$field] = json_decode($content[$field], true, 512,JSON_THROW_ON_ERROR);
            }
        }
        return $content;
    }
}
