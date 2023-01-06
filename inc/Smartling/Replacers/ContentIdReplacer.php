<?php

namespace Smartling\Replacers;

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

    public function processOnDownload(mixed $originalValue, mixed $translatedValue, ?SubmissionEntity $submission): mixed
    {
        if ($submission === null) {
            throw new \InvalidArgumentException('Submission must not be null');
        }
        $relatedSubmission = $this->submissionManager->findOne([
            SubmissionEntity::FIELD_SOURCE_BLOG_ID => $submission->getSourceBlogId(),
            SubmissionEntity::FIELD_TARGET_BLOG_ID => $submission->getTargetBlogId(),
            SubmissionEntity::FIELD_SOURCE_ID => $translatedValue,
        ]);
        if ($relatedSubmission === null) {
            $this->logger->debug("No related submissions found while trying to replace content id for submissionId=\"{$submission->getId()}\", skipping");
            return $translatedValue;
        }

        $targetId = $relatedSubmission->getTargetId();
        $this->logger->debug("ContentIdReplacer found replacement for submissionId=\"{$submission->getId()}, originalValue=\"$originalValue\", translatedValue=\"$targetId\"");
        if ($targetId !== 0) {
            settype($targetId, gettype($originalValue));
            return $targetId;
        }

        return $translatedValue;
    }

    public function processOnUpload(mixed $value, ?SubmissionEntity $submission = null): mixed
    {
        return $value;
    }
}
