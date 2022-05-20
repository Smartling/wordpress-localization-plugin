<?php

namespace Smartling\ContentTypes;

use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Helpers\QueryBuilder\Condition\Condition;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBlock;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBuilder;
use Smartling\Helpers\QueryBuilder\QueryBuilder;
use Smartling\Helpers\SiteHelper;
use Smartling\Submissions\SubmissionEntity;

class ExternalContentAioseo implements ContentTypePluggableInterface
{
    private FieldsFilterHelper $fieldsFilterHelper;
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
        'images',
        'priority',
        'frequency',
        'videos',
        'video_thumbnail',
    ];
    private array $stripTagsFields = [
        'title',
        'description',
        'og_title',
        'og_description',
        'og_article_section',
    ];

    public function __construct(SiteHelper $siteHelper, SmartlingToCMSDatabaseAccessWrapperInterface $db, FieldsFilterHelper $fieldsFilterHelper)
    {
        $this->db = $db;
        $this->fieldsFilterHelper = $fieldsFilterHelper;
        $this->siteHelper = $siteHelper;
    }

    public function getContentFields(SubmissionEntity $submission, bool $raw): array
    {
        $submission->assertHasSource();
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

    public function getPluginPath(): string
    {
        return 'all-in-one-seo-pack/all_in_one_seo_pack.php';
    }

    public function setContentFields(array $content, SubmissionEntity $submission): void
    {
        foreach ($this->jsonFields as $field) {
            if (array_key_exists($field, $content)) {
                $content[$field] = json_encode($content[$field], JSON_THROW_ON_ERROR);
            }
        }
        unset($content['id']);
        $content['post_id'] = $submission->getTargetId();
        $this->siteHelper->withBlog($submission->getTargetBlogId(), function () use ($content, $submission) {
            if (($this->db->fetchPrepared("select count(*) c from {$this->db->getPrefix()}aioseo_posts where post_id = %d", $submission->getTargetId())[0]['c'] ?? null) === '0') {
                $this->db->query(QueryBuilder::buildInsertQuery($this->db->getPrefix() . 'aioseo_posts', $content));
            } else {
                $conditionBlock = ConditionBlock::getConditionBlock();
                $conditionBlock->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, 'post_id', [$submission->getTargetId()]));
                $this->db->query(QueryBuilder::buildUpdateQuery($this->db->getPrefix() . 'aioseo_posts', $content, $conditionBlock));
            }
        });
    }

    public function stripTags(string $string): string
    {
        $parts = explode(' ', $string);
        $result = [];
        foreach ($parts as $part) {
            if (strpos($part, '#') !== 0) {
                $result[] = $part;
            }
        }

        return implode(' ', $result);
    }

    public function transformContentForUpload(array $content): array
    {
        foreach ($this->removeFields as $field) {
            unset($content[$field]);
        }
        foreach ($this->stripTagsFields as $field) {
            $content[$field] = $this->stripTags($content[$field]);
        }
        foreach ($this->jsonFields as $field) {
            $content[$field] = json_decode($content[$field], true, 512, JSON_THROW_ON_ERROR);
        }
        if (array_key_exists('focus', $content['keyphrases'])) {
            $content['keyphrases']['focus'] = ['keyphrase' => $content['keyphrases']['focus']['keyphrase']];
            unset($content['keyphrases']['additional']);
        }
        foreach ($this->jsonFields as $field) {
            $content[$field] = $this->fieldsFilterHelper->flattenArray($content[$field]);
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
