<?php

namespace Smartling\Tests\Smartling\Base;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Smartling\Base\SmartlingCoreUploadTrait;
use Smartling\DbAl\WordpressContentEntities\PostEntityStd;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\DecodedXml;
use Smartling\Helpers\FieldsFilterHelper;
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

    /**
     * @param ContentHelper $contentHelper
     * @param FieldsFilterHelper $fieldsFilterHelper
     * @param SettingsManager $settingsManager
     * @param SubmissionManager $submissionManager
     */
    public function __construct(ContentHelper $contentHelper, FieldsFilterHelper $fieldsFilterHelper, SettingsManager $settingsManager, SubmissionManager $submissionManager)
    {
        $this->contentHelper = $contentHelper;
        $this->fieldsFilterHelper = $fieldsFilterHelper;
        $this->settingsManager = $settingsManager;
        $this->submissionManager = $submissionManager;
    }

    public function getLogger()
    {
        return new NullLogger();
    }

    public function prepareFieldProcessorValues()
    {
    }

    public function prepareRelatedSubmissions()
    {
    }

    public function getContentHelper()
    {
        return $this->contentHelper;
    }

    public function getFieldsFilter()
    {
        return $this->fieldsFilterHelper;
    }

    public function getSettingsManager()
    {
        return $this->settingsManager;
    }

    public function getSubmissionManager()
    {
        return $this->submissionManager;
    }
}

class SmartlingCoreUploadTraitTest extends TestCase
{
    public function setUp()
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
        $xmlHelper = $this->getMock(XmlHelper::class);
        $xmlHelper->method('xmlDecode')->willReturn(new DecodedXml(
            ['meta' => $translatedFields],
            ['meta' => ['metaNotToTranslate' => 's:8:"Original"', 'metaToTranslate' => 'Original']]
        ));

        $contentHelper->expects(self::never())->method('removeTargetMetadata');
        $contentHelper->expects(self::once())->method('writeTargetMetadata')->with($submission, $translatedFields);
        self::assertEquals([], $x->applyXML($submission, ' ', $xmlHelper));
    }

    public function testApplyXmlCleanMetadata()
    {
        $submission = new SubmissionEntity();
        $contentHelper = $this->getMockBuilder(ContentHelper::class)->disableOriginalConstructor()->getMock();
        $contentHelper->method('readSourceContent')->willReturnArgument(0);
        $contentHelper->method('readSourceMetadata')->willReturn([]);
        $contentHelper->method('readTargetContent')->willReturn(new PostEntityStd());

        $fieldsFilterHelper = $this->getMockBuilder(FieldsFilterHelper::class)->disableOriginalConstructor()->getMock();
        $fieldsFilterHelper->method('processStringsAfterDecoding')->willReturnArgument(0);
        $fieldsFilterHelper->method('applyTranslatedValues')->willReturnArgument(2);

        $profile = $this->getMockBuilder(ConfigurationProfileEntity::class)->disableOriginalConstructor()->getMock();
        $profile->method('getCleanMetadataOnDownload')->willReturn(1);

        $settingsManager = $this->getMockBuilder(SettingsManager::class)->disableOriginalConstructor()->getMock();
        $settingsManager->method('getSingleSettingsProfile')->willReturn($profile);

        $submissionManager = $this->getMockBuilder(SubmissionManager::class)->disableOriginalConstructor()->getMock();
        $submissionManager->method('storeEntity')->willReturnArgument(0);

        $x = new SmartlingCoreUpload($contentHelper, $fieldsFilterHelper, $settingsManager, $submissionManager);
        $xmlHelper = $this->getMock(XmlHelper::class);
        $xmlHelper->method('xmlDecode')->willReturn(new DecodedXml(
            ['meta' => ['metaToTranslate' => '~Translated~']],
            ['meta' => ['sourceMetaField' => 'set', 'metaToTranslate' => 'Original']]
        ));

        $contentHelper->expects(self::once())->method('removeTargetMetadata');
        $contentHelper->expects(self::once())->method('writeTargetMetadata')->with($submission, ['sourceMetaField' => 'set', 'metaToTranslate' => '~Translated~']);
        self::assertEquals([], $x->applyXML($submission, ' ', $xmlHelper));
    }
}
