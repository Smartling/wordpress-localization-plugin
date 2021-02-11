<?php

namespace Smartling\Tests\Smartling\Base;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Smartling\Base\SmartlingCoreUploadTrait;
use Smartling\DbAl\WordpressContentEntities\PostEntityStd;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\DecodedXml;
use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Helpers\GutenbergBlockHelper;
use Smartling\Helpers\PostContentHelper;
use Smartling\Helpers\XmlHelper;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\Tests\Mocks\WordpressFunctionsMockHelper;

class SmartlingCoreUpload {
    use SmartlingCoreUploadTrait;

    private $contentHelper;
    private $fieldsFilterHelper;
    private $settingsManager;
    private $submissionManager;

    public function __construct(ContentHelper $contentHelper, FieldsFilterHelper $fieldsFilterHelper, SettingsManager $settingsManager, SubmissionManager $submissionManager)
    {
        $this->contentHelper = $contentHelper;
        $this->fieldsFilterHelper = $fieldsFilterHelper;
        $this->settingsManager = $settingsManager;
        $this->submissionManager = $submissionManager;
    }

    public function getLogger(): NullLogger
    {
        return new NullLogger();
    }

    public function prepareFieldProcessorValues()
    {
    }

    public function prepareRelatedSubmissions()
    {
    }

    public function getContentHelper(): ContentHelper
    {
        return $this->contentHelper;
    }

    public function getFieldsFilter(): FieldsFilterHelper
    {
        return $this->fieldsFilterHelper;
    }

    public function getSettingsManager(): SettingsManager
    {
        return $this->settingsManager;
    }

    public function getSubmissionManager(): SubmissionManager
    {
        return $this->submissionManager;
    }
}

class SmartlingCoreUploadTraitTest extends TestCase
{
    public function setUp(): void
    {
        WordpressFunctionsMockHelper::injectFunctionsMocks();
    }

    public function testApplyXmlNoCleanMetadata()
    {
        $submission = new SubmissionEntity();
        $translatedFields = ['metaNotToTranslate' => 's:8:"Translated"', 'metaToTranslate' => '~Translated~'];

        $contentHelper = $this->getMockBuilder(ContentHelper::class)->disableOriginalConstructor()->getMock();
        $contentHelper->method('readSourceContent')->willReturnArgument(0);
        $contentHelper->method('readSourceMetadata')->willReturn([]);
        $contentHelper->method('readTargetContent')->willReturn(new PostEntityStd());

        $fieldsFilterHelper = $this->getMockBuilder(FieldsFilterHelper::class)->disableOriginalConstructor()->getMock();
        $fieldsFilterHelper->method('processStringsAfterDecoding')->willReturnArgument(0);
        $fieldsFilterHelper->method('applyTranslatedValues')->willReturnArgument(2);

        $settingsManager = $this->getMockBuilder(SettingsManager::class)->disableOriginalConstructor()->getMock();
        $settingsManager->method('getSingleSettingsProfile')->willReturn(new ConfigurationProfileEntity());

        $submissionManager = $this->getMockBuilder(SubmissionManager::class)->disableOriginalConstructor()->getMock();
        $submissionManager->method('storeEntity')->willReturnArgument(0);

        $x = new SmartlingCoreUpload($contentHelper, $fieldsFilterHelper, $settingsManager, $submissionManager);
        $xmlHelper = $this->createMock(XmlHelper::class);
        $xmlHelper->method('xmlDecode')->willReturn(new DecodedXml(
            ['meta' => $translatedFields],
            ['meta' => ['metaNotToTranslate' => 's:8:"Original"', 'metaToTranslate' => 'Original']]
        ));

        $contentHelper->expects(self::never())->method('removeTargetMetadata');
        $contentHelper->expects(self::once())->method('writeTargetMetadata')->with($submission, $translatedFields);
        self::assertEquals([], $x->applyXML($submission, ' ', $xmlHelper, new PostContentHelper(new GutenbergBlockHelper())));
    }

    public function testApplyXmlCleanMetadata()
    {
        $submission = new SubmissionEntity();
        $submission->setLockedFields(['meta/locked']);
        $submission->setTargetId('1');
        $contentHelper = $this->getMockBuilder(ContentHelper::class)->disableOriginalConstructor()->getMock();
        $contentHelper->method('readSourceContent')->willReturnArgument(0);
        $contentHelper->method('readSourceMetadata')->willReturn([]);
        $contentHelper->method('readTargetContent')->willReturn(new PostEntityStd());
        $contentHelper->method('readTargetMetadata')->willReturn(['locked' => 'locked', 'unlocked' => 'unlocked']);

        $fieldsFilterHelper = $this->createPartialMock(FieldsFilterHelper::class, ['applyTranslatedValues', 'getLogger', 'processStringsAfterDecoding']);
        $fieldsFilterHelper->method('processStringsAfterDecoding')->willReturnArgument(0);
        $fieldsFilterHelper->method('applyTranslatedValues')->willReturnArgument(2);
        $fieldsFilterHelper->method('getLogger')->willReturn(new NullLogger());

        $profile = $this->getMockBuilder(ConfigurationProfileEntity::class)->disableOriginalConstructor()->getMock();
        $profile->method('getCleanMetadataOnDownload')->willReturn(1);
        $profile->method('getFilterFieldNameRegExp')->willReturn(true);
        $profile->method('getFilterSkipArray')->willReturn(['excluded']);

        $settingsManager = $this->getMockBuilder(SettingsManager::class)->disableOriginalConstructor()->getMock();
        $settingsManager->method('getSingleSettingsProfile')->willReturn($profile);

        $submissionManager = $this->getMockBuilder(SubmissionManager::class)->disableOriginalConstructor()->getMock();
        $submissionManager->method('storeEntity')->willReturnArgument(0);

        $x = new SmartlingCoreUpload($contentHelper, $fieldsFilterHelper, $settingsManager, $submissionManager);
        $xmlHelper = $this->createMock(XmlHelper::class);
        $xmlHelper->method('xmlDecode')->willReturn(new DecodedXml(
            ['meta' => ['metaToTranslate' => '~Translated~']],
            ['meta' => ['excludedField' => 'excluded', 'sourceMetaField' => 'set', 'metaToTranslate' => 'Original']]
        ));

        $contentHelper->expects(self::once())->method('removeTargetMetadata');
        $contentHelper->expects(self::once())->method('writeTargetMetadata')->with($submission, ['sourceMetaField' => 'set', 'metaToTranslate' => '~Translated~', 'locked' => 'locked']);
        self::assertEquals([], $x->applyXML($submission, ' ', $xmlHelper, new PostContentHelper(new GutenbergBlockHelper())));
    }
}
