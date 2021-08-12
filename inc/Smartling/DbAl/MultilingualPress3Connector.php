<?php

namespace Smartling\DbAl;

use Inpsyde\MultilingualPress\Core\Admin\SiteSettingsRepository;
use Inpsyde\MultilingualPress\Framework\Api\ContentRelations;
use Smartling\Helpers\QueryBuilder\Condition\Condition;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBlock;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBuilder;
use Smartling\Helpers\QueryBuilder\QueryBuilder;
use Smartling\Submissions\SubmissionEntity;
use function Inpsyde\MultilingualPress\resolve;

class MultilingualPress3Connector extends MultilingualPressAbstract
{
    public function isActive(): bool
    {
        try {
            resolve(ContentRelations::class);
            return true;
        } catch (\Error $e) {
            return false;
        }
    }

    public function getBlogLocaleById(int $blogId): string
    {
        try {
            return resolve(SiteSettingsRepository::class)->siteLanguageTag($blogId);
        } catch (\Error $e) {
            return '';
        }
    }

    public function linkObjects(SubmissionEntity $submission): bool
    {
        if ($this->isActive()) {
            try {
                /**
                 * @var ContentRelations $contentRelations
                 */
                $contentRelations = resolve(ContentRelations::class);
                $contentIds = $this->getContentIds($submission);
                $relationshipId = $contentRelations->relationshipId($contentIds, $submission->getContentType());
                if ($relationshipId > 0) {
                    return $contentRelations->saveRelation($relationshipId, $submission->getTargetBlogId(), $submission->getTargetId());
                }

                $contentRelations->createRelationship($contentIds, $submission->getContentType());
                return true;
            } catch (\Exception $e) {
                return false;
            }
        }
        return false;
    }

    public function unlinkObjects(SubmissionEntity $submission): bool
    {
        if ($this->isActive()) {
            try {
                /**
                 * @var ContentRelations $contentRelations
                 */
                $contentRelations = resolve(ContentRelations::class);
                return $contentRelations->deleteRelation($this->getContentIds($submission), $submission->getContentType());
            } catch (\Exception $e) {
                return false;
            }
        }
        return false;
    }

    public function getBlogNameByLocale(string $locale): string
    {
        global $wpdb;
        $tableName = 'mlp_languages';
        $condition = ConditionBlock::getConditionBlock();
        $condition->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, 'locale', [$locale]));
        $query = QueryBuilder::buildSelectQuery(
            $wpdb->base_prefix . $tableName,
            ['english_name'],
            $condition,
            [],
            ['page' => 1, 'limit' => 1]
        );
        $r = $wpdb->get_results($query, ARRAY_A);

        return 1 === count($r) ? $r[0]['english_name'] : $locale;
    }

    private function getContentIds(SubmissionEntity $submission): array
    {
        return [
            $submission->getSourceBlogId() => $submission->getSourceId(),
            $submission->getTargetBlogId() => $submission->getTargetId(),
        ];
    }
}
