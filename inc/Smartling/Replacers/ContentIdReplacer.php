<?php

namespace Smartling\Replacers;

use Psr\Log\LoggerInterface;
use Smartling\Helpers\ArrayHelper;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

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
     * @param mixed $value
     * @return mixed
     */
    public function processOnDownload(SubmissionEntity $submission, $value)
    {
        $sourceBlogId = $submission->getSourceBlogId();
        $targetBlogId = $submission->getTargetBlogId();
        $relatedSubmissions = $this->submissionManager->find([
            SubmissionEntity::FIELD_SOURCE_BLOG_ID => $sourceBlogId,
            SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlogId,
            SubmissionEntity::FIELD_SOURCE_ID => $value,
        ]);
        if (count($relatedSubmissions) === 0) {
            $this->logger->debug("No related submissions found while trying to replace content id for submissionId=\"{$submission->getId()}\", skipping");
            return $value;
        }

        if (count($relatedSubmissions) > 1) {
            $this->logger->warning("More than a single submission found while trying to replace content id for submissionId=\"{$submission->getId()}\", skipping");
            return $value;
        }

        $targetId = ArrayHelper::first($relatedSubmissions)->getTargetId();
        if ($targetId !== 0) {
            settype($targetId, gettype($value));
            return $targetId;
        }

        return $value;
    }
}
