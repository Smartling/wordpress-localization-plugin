<?php

namespace Smartling\Replacers;

use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class ContentIdReplacer implements ReplacerInterface
{
    private string $contentType;
    private LocalizationPluginProxyInterface $localizationProxy;
    private SubmissionManager $submissionManager;

    public function __construct(LocalizationPluginProxyInterface $localizationProxy, SubmissionManager $submissionManager, string $contentType)
    {
        $this->contentType = $contentType;
        $this->localizationProxy = $localizationProxy;
        $this->submissionManager = $submissionManager;
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
}
