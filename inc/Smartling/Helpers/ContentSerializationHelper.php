<?php

namespace Smartling\Helpers;

use Smartling\Bootstrap;
use Smartling\Processors\ContentEntitiesIOFactory;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;

/**
 * Class ContentSerializationHelper
 * @package Smartling\Helpers
 */
class ContentSerializationHelper
{
    /**
     * @param ContentEntitiesIOFactory $ioFactory
     * @param SiteHelper               $siteHelper
     * @param SettingsManager          $settingsManager
     * @param SubmissionEntity         $submission
     *
     * @return string
     * @throws \Smartling\Exception\SmartlingConfigException
     * @throws \Smartling\Exception\SmartlingInvalidFactoryArgumentException
     * @throws \Smartling\Exception\SmartlingDirectRunRuntimeException
     * @throws \Smartling\Exception\BlogNotFoundException
     */
    public static function calculateHash(ContentEntitiesIOFactory $ioFactory, SiteHelper $siteHelper, SettingsManager $settingsManager, SubmissionEntity $submission)
    {
        $collectedContent = self::collectSubmissionSourceContent($ioFactory, $siteHelper, $submission);

        $filteredData = self::filterCollectedContent($collectedContent, $settingsManager, $submission);

        return md5(serialize($filteredData));
    }

    /**
     * @param array            $collectedContent
     * @param SettingsManager  $settingsManager
     * @param SubmissionEntity $submission
     *
     * @return array
     * @throws \Smartling\Exception\SmartlingConfigException
     */
    public static function filterCollectedContent(array $collectedContent, SettingsManager $settingsManager, SubmissionEntity $submission)
    {
        self::prepareFieldProcessorValues($settingsManager, $submission);

        return XmlEncoder::filterRawSource($collectedContent);
    }


    /**
     * @param ContentEntitiesIOFactory $ioFactory
     * @param SiteHelper               $siteHelper
     * @param SubmissionEntity         $submission
     *
     * @return array
     * @throws \Smartling\Exception\SmartlingDirectRunRuntimeException
     * @throws \Smartling\Exception\SmartlingInvalidFactoryArgumentException
     * @throws \Smartling\Exception\BlogNotFoundException
     */
    public static function collectSubmissionSourceContent(ContentEntitiesIOFactory $ioFactory, SiteHelper $siteHelper, SubmissionEntity $submission)
    {
        $ioWrapper = $ioFactory->getMapper($submission->getContentType());
        $needBlogSwitch = $siteHelper->getCurrentBlogId() !== $submission->getSourceBlogId();
        if ($needBlogSwitch) {
            $siteHelper->switchBlogId($submission->getSourceBlogId());
        }
        $contentEntity = $ioWrapper->get($submission->getSourceId());
        $source = [
            'entity' => $contentEntity->toArray(),
            'meta'   => $contentEntity->getMetadata(),
        ];
        $source['meta'] = $source['meta'] ? : [];
        if ($needBlogSwitch) {
            $siteHelper->restoreBlogId();
        }

        return $source;
    }

    /**
     * Sets filed processor rules depending on profile settings
     *
     * @param SettingsManager  $settingsManager
     * @param SubmissionEntity $submission
     *
     * @throws \Smartling\Exception\SmartlingConfigException
     */
    public static function prepareFieldProcessorValues(SettingsManager $settingsManager, SubmissionEntity $submission)
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
            /**
             * @var ConfigurationProfileEntity $profile
             */
            $profile = reset($profiles);

            $filter['ignore'] = array_map('trim', explode(PHP_EOL, $profile->getFilterSkip()));
            $filter['key']['seo'] = array_map('trim', explode(PHP_EOL, $profile->getFilterFlagSeo()));
            $filter['copy']['name'] = array_map('trim', explode(PHP_EOL, $profile->getFilterCopyByFieldName()));
            $filter['copy']['regexp'] = array_map('trim', explode(PHP_EOL, $profile->getFilterCopyByFieldValueRegex()));
        }

        Bootstrap::getContainer()->setParameter('field.processor', $filter);
    }
}