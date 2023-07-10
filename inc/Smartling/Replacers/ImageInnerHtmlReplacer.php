<?php

namespace Smartling\Replacers;

use Smartling\Models\GutenbergBlock;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\Vendor\Psr\Log\LoggerInterface;

class ImageInnerHtmlReplacer extends DoNothingContentReplacer {
    private LoggerInterface $logger;
    private SubmissionManager $submissionManager;
    public function __construct(SubmissionManager $submissionManager)
    {
        $this->logger = MonologWrapper::getLogger();
        $this->submissionManager = $submissionManager;
    }

    public function processContentOnDownload(GutenbergBlock $original, GutenbergBlock $translated, ?SubmissionEntity $submission): GutenbergBlock
    {
        if ($submission === null) {
            return $translated;
        }
        $relatedSubmission = $this->submissionManager->findOne([
            SubmissionEntity::FIELD_CONTENT_TYPE => $submission->getContentType(),
            SubmissionEntity::FIELD_SOURCE_BLOG_ID => $submission->getSourceBlogId(),
            SubmissionEntity::FIELD_SOURCE_ID => $submission->getSourceId(),
            SubmissionEntity::FIELD_TARGET_BLOG_ID => $submission->getTargetBlogId(),
        ]);
        if ($relatedSubmission === null) {
            return $translated;
        }
        $innerContent = [];
        foreach ($translated->getInnerContent() as $string) {
            $innerContent[] = preg_replace("/<img(.+)? class=\"([^\"]+)?wp-image-{$original->getAttributes()['id']}([^\"]+)?\"/", "<img\$1 class=\"\$2wp-image-{$relatedSubmission->getTargetId()}\$3\"", $string);
        }
        $this->logger->info("inner content changed by " . self::class);
        return $translated->withInnerContent($innerContent);
    }
}
