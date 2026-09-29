<?php

namespace Smartling\Helpers;

use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;

class ContentSerializationHelper
{
    use LoggerSafeTrait;

    public function __construct(private ContentHelper $contentHelper, private SettingsManager $settingsManager)
    {
    }

    public function getRemoveFields(): array
    {
        return [
            'entity' => [
                'hash',
                'post_author',
                'post_date',
                'post_date_gmt',
                'post_password',
                'post_modified',
                'post_modified_gmt',
            ],
            'meta' => [
                '_edit_lock',
                '_edit_last',
                '_encloseme',
                '_pingme',
            ],
        ];
    }

    /**
     * Fields that change when a draft is published without any change in content to translate.
     * Ignoring them prevents marking translated submissions outdated on publishing.
     * Submissions hashed before these fields were ignored are matched with calculateLegacyHash().
     */
    private function getPublishingFields(): array
    {
        return [
            'entity' => [
                'post_status',
                'post_name',
            ],
        ];
    }

    private function cleanUpFields(array $fields, bool $legacy = false): array
    {
        $removeFields = $legacy ? $this->getRemoveFields() : array_merge_recursive($this->getRemoveFields(), $this->getPublishingFields());
        foreach ($removeFields as $part => $keys) {
            foreach ($keys as $key) {
                if (array_key_exists($part, $fields) && array_key_exists($key, $fields[$part])) {
                    unset($fields[$part][$key]);
                }
            }
        }

        return $fields;
    }

    public function calculateHash(SubmissionEntity $submission): string
    {
        return $this->calculateHashInternal($submission, false);
    }

    /**
     * Hash as it was calculated before publishing related fields were ignored
     */
    public function calculateLegacyHash(SubmissionEntity $submission): string
    {
        return $this->calculateHashInternal($submission, true);
    }

    private function calculateHashInternal(SubmissionEntity $submission, bool $legacy): string
    {
        $cache = RuntimeCacheHelper::getInstance();
        $key = implode(
            ':',
            [
                $submission->getSourceBlogId(),
                $submission->getContentType(),
                $submission->getSourceId(),
            ]
        );
        $cacheGroup = $legacy ? 'legacyHashCalculator' : 'hashCalculator';

        if (false === ($cached = $cache->get($key, $cacheGroup))) {
            $collectedContent = $this->collectSubmissionSourceContent($submission);
            $collectedContent = $this->cleanUpFields($collectedContent, $legacy);
            $serializedContent = serialize($collectedContent);
            $this->getLogger()->debug(vsprintf('Calculating hash for submission=%s using data=%s', [$submission->getId(), base64_encode($serializedContent)]));
            $hash = md5($serializedContent);
            $cached = $hash;
            $cache->set($key, $hash, $cacheGroup);
        }

        return $cached;
    }

    private function collectSubmissionSourceContent(SubmissionEntity $submission): array
    {
        $source = [
            'entity' => $this->contentHelper->readSourceContent($submission)->toArray(),
            'meta'   => $this->contentHelper->readSourceMetadata($submission),
        ];
        $source['meta'] = $source['meta'] ? : [];

        return $source;
    }

    public function prepareFieldProcessorValues(SubmissionEntity $submission): array
    {
        $profiles = $this->settingsManager->findEntityByMainLocale($submission->getSourceBlogId());

        $filter = [
            'ignore' => [],
            'key'    => [
                'seo' => [],
            ],
            'copy'   => [
                'name'   => [],
                'regexp' => [],
            ],
        ];

        if (0 < count($profiles)) {
            $profile = ArrayHelper::first($profiles);

            $filter['ignore'] = $profile->getFilterSkipArray();
            $filter['key']['seo'] = array_map('trim', explode(PHP_EOL, $profile->getFilterFlagSeo()));
            $filter['copy']['name'] = array_map('trim', explode(PHP_EOL, $profile->getFilterCopyByFieldName()));
            $filter['copy']['regexp'] = array_map('trim', explode(PHP_EOL, $profile->getFilterCopyByFieldValueRegex()));

            LogContextMixinHelper::addToContext('projectId', $profile->getProjectId());
        }

        return $filter;
    }
}
