<?php

namespace Smartling\Replacers;

use Psr\Log\LoggerInterface;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Exception\SmartlingDataReadException;
use Smartling\Helpers\TranslationHelper;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class ContentIdReplacer implements ReplacerInterface
{
    private string $contentType;
    private LocalizationPluginProxyInterface $localizationProxy;
    private LoggerInterface $logger;
    private SubmissionManager $submissionManager;
    private TranslationHelper $translationHelper;

    public function __construct(LocalizationPluginProxyInterface $localizationProxy, SubmissionManager $submissionManager, TranslationHelper $translationHelper, string $contentType)
    {
        $this->contentType = $contentType;
        $this->localizationProxy = $localizationProxy;
        $this->logger = MonologWrapper::getLogger(self::class);
        $this->submissionManager = $submissionManager;
        $this->translationHelper = $translationHelper;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function getLabel(): string
    {
        return "Related: $this->contentType";
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public function processOnDownload(SubmissionEntity $submission, $value)
    {
        $sourceBlogId = $submission->getSourceBlogId();
        $targetBlogId = $submission->getTargetBlogId();

        $targetId = $this->submissionManager->getSubmissionEntity($this->contentType, $sourceBlogId, (int)$value, $targetBlogId, $this->localizationProxy)->getTargetId();
        if ($targetId !== 0) {
            settype($targetId, gettype($value));
            return $targetId;
        }

        return $value;
    }

    /**
     * @param mixed $value
     * @return mixed
     * @throws SmartlingDataReadException
     */
    public function processOnUpload(SubmissionEntity $submission, $value)
    {
        $sourceBlogId = $submission->getSourceBlogId();
        $targetBlogId = $submission->getTargetBlogId();

        if (!empty($value) && is_numeric($value)) {
            $dataType = gettype($value);
            if ($this->translationHelper->isRelatedSubmissionCreationNeeded(
                $this->contentType,
                $sourceBlogId,
                (int)$value,
                $targetBlogId
            )) {
                $targetId = $this->translationHelper->tryPrepareRelatedContent(
                    $this->contentType,
                    $sourceBlogId,
                    (int)$value,
                    $targetBlogId,
                    $submission->getJobInfoWithBatchUid()
                )->getTargetId();

                if ($dataType === 'string') {
                    $value = (string)$targetId;
                }
            }
        }

        return $value;
    }
}
