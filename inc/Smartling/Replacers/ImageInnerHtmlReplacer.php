<?php

namespace Smartling\Replacers;

use Smartling\Models\GutenbergBlock;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class ImageInnerHtmlReplacer extends DoNothingContentReplacer {
    private SubmissionManager $submissionManager;
    public function __construct(SubmissionManager $submissionManager)
    {
        $this->submissionManager = $submissionManager;
    }

    public function processContentOnDownload(GutenbergBlock $original, GutenbergBlock $translated, ?SubmissionEntity $submission): GutenbergBlock
    {
        if ($submission === null) {
            return $translated;
        }
        if (!array_key_exists('id', $original->getAttributes())) {
            return $translated;
        }
        $relatedSubmission = $this->submissionManager->findOne([
            SubmissionEntity::FIELD_CONTENT_TYPE => 'attachment',
            SubmissionEntity::FIELD_SOURCE_BLOG_ID => $submission->getSourceBlogId(),
            SubmissionEntity::FIELD_SOURCE_ID => $original->getAttributes()['id'],
            SubmissionEntity::FIELD_TARGET_BLOG_ID => $submission->getTargetBlogId(),
        ]);
        if ($relatedSubmission === null) {
            return $translated;
        }
        $innerContent = [];
        foreach ($translated->getInnerContent() as $string) {
            $innerContent[] = preg_replace("/<img(.+)? class=\"([^\"]+)?wp-image-{$original->getAttributes()['id']}([^\"]+)?\"/", "<img\$1 class=\"\$2wp-image-{$relatedSubmission->getTargetId()}\$3\"", $string);
        }
        return $translated->withInnerContent($innerContent);
    }
}
