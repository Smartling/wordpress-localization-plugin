<?php

namespace Smartling\Replacers;

use Smartling\Helpers\ArrayHelper;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\Vendor\Psr\Log\LoggerInterface;

class ContentIdReplacer implements ReplacerInterface
{
    private LoggerInterface $logger;
    private SubmissionManager $submissionManager;

    public function __construct(SubmissionManager $submissionManager)
    {
        $this->logger = MonologWrapper::getLogger();
        $this->submissionManager = $submissionManager;
    }

    public function getLabel(): string
    {
        return "Related: Post based content";
    }

    /**
     * @param mixed $originalValue
     * @param mixed $translatedValue
     *
     * @return mixed
     */
    public function processOnDownload($originalValue, $translatedValue, ?SubmissionEntity $submission)
    {
        if ($submission === null) {
            throw new \InvalidArgumentException('Submission must not be null');
        }
        $sourceBlogId = $submission->getSourceBlogId();
        $targetBlogId = $submission->getTargetBlogId();
        $relatedSubmissions = $this->submissionManager->find([
            SubmissionEntity::FIELD_SOURCE_BLOG_ID => $sourceBlogId,
            SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlogId,
            SubmissionEntity::FIELD_SOURCE_ID => $translatedValue,
        ]);
        if (count($relatedSubmissions) === 0) {
            $this->logger->debug("No related submissions found while trying to replace content id for submissionId=\"{$submission->getId()}\", skipping");
            return $translatedValue;
        }

        if (count($relatedSubmissions) > 1) {
            $this->logger->warning("More than a single submission found while trying to replace content id for submissionId=\"{$submission->getId()}\", skipping");
            return $translatedValue;
        }

        $targetId = ArrayHelper::first($relatedSubmissions)->getTargetId();
        $this->logger->debug("ContentIdReplacer found replacement for submissionId=\"{$submission->getId()}, originalValue=\"$originalValue\", translatedValue=\"$targetId\"");
        if ($targetId !== 0) {
            settype($targetId, gettype($originalValue));
            return $targetId;
        }

        return $translatedValue;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public function processOnUpload($value, ?SubmissionEntity $submission = null)
    {
        return $value;
    }
}
