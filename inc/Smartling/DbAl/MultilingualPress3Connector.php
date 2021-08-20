<?php

namespace Smartling\DbAl;

use Inpsyde\MultilingualPress\Core\Admin\SiteSettingsRepository;
use Inpsyde\MultilingualPress\Framework\Api\ContentRelations;
use Smartling\Base\ExportedAPI;
use Smartling\Submissions\SubmissionEntity;
use function Inpsyde\MultilingualPress\resolve;

class MultilingualPress3Connector extends MultilingualPressConnector implements LocalizationPluginProxyInterface
{
    public function addHooks(): void
    {
        add_action(ExportedAPI::ACTION_SMARTLING_SEND_FILE_FOR_TRANSLATION, [$this, 'linkObjects']);
    }

    public function getBlogLocaleById(int $blogId): string
    {
        try {
            return resolve(SiteSettingsRepository::class)->siteLanguageTag($blogId);
        } catch (\Error $e) {
            return '';
        }
    }

    public function getBlogNameByLocale(string $locale): string
    {
        global $wpdb;
        return $this->getEnglishNameFromMlpLanguagesTable($locale, 'locale', $wpdb);
    }

    public function isActive(): bool
    {
        try {
            resolve(ContentRelations::class);
            return true;
        } catch (\Error $e) {
            return false;
        }
    }

    public function linkObjects(SubmissionEntity $submission): bool
    {
        if ($this->isActive()) {
            try {
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
                return resolve(ContentRelations::class)
                    ->deleteRelation($this->getContentIds($submission), $submission->getContentType());
            } catch (\Exception $e) {
                return false;
            }
        }
        return false;
    }

    private function getContentIds(SubmissionEntity $submission): array
    {
        return [
            $submission->getSourceBlogId() => $submission->getSourceId(),
            $submission->getTargetBlogId() => $submission->getTargetId(),
        ];
    }
}
