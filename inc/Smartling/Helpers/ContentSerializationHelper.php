<?php

namespace Smartling\Helpers;

use Smartling\Bootstrap;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Vendor\Psr\Log\LoggerInterface;

class ContentSerializationHelper
{
    private LoggerInterface $logger;
    private ContentHelper $contentHelper;

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    public function __construct(ContentHelper $contentHelper)
    {
        $this->logger = MonologWrapper::getLogger(get_called_class());
        $this->contentHelper = $contentHelper;
    }

    private function cleanUpFields(array $fields): array
    {
        $toRemove = [
            'entity' => [
                'hash',
                'post_author',
                'post_date',
                'post_date_gmt',
                'post_password',
                'post_modified',
                'post_modified_gmt',
            ],
            'meta'   => [
                '_edit_lock',
                '_edit_last',
                '_encloseme',
                '_pingme',
            ],
        ];

        foreach ($toRemove as $part => $keys) {
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

    /**
     * Sets field processor rules depending on profile settings
     */
    public static function prepareFieldProcessorValues(SettingsManager $settingsManager, SubmissionEntity $submission): void
    {
        $profiles = $settingsManager->findEntityByMainLocale($submission->getSourceBlogId());

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

        Bootstrap::getContainer()->setParameter('field.processor', $filter);
    }
}
