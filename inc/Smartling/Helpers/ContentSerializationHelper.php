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

    private function cleanUpFields(array $fields): array
    {
        foreach ($this->getRemoveFields() as $part => $keys) {
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
        $cache = RuntimeCacheHelper::getInstance();
        $key = implode(
            ':',
            [
                $submission->getSourceBlogId(),
                $submission->getContentType(),
                $submission->getSourceId(),
            ]
        );

        if (false === ($cached = $cache->get($key, 'hashCalculator'))) {
            $collectedContent = $this->collectSubmissionSourceContent($submission);
            $collectedContent = $this->cleanUpFields($collectedContent);
            $serializedContent = serialize($collectedContent);
            $this->getLogger()->debug(vsprintf('Calculating hash for submission=%s using data=%s', [$submission->getId(), base64_encode($serializedContent)]));
            $hash = md5($serializedContent);
            $cached = $hash;
            $cache->set($key, $hash, 'hashCalculator');
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
