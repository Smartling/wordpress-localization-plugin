<?php

namespace Smartling\Helpers\MetaFieldProcessor;

use Smartling\Base\ExportedAPI;
use Smartling\Helpers\Parsers\IntegerParser;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Submissions\SubmissionEntity;

/**
 * Class ReferencedCategoryProcessor
 * @package Smartling\Helpers\MetaFieldProcessor
 */
class ReferencedCategoryProcessor extends ReferencedPageProcessor
{

    /**
     * @param SubmissionEntity $submission
     *
     * @throws \Smartling\Exception\SmartlingDataReadException
     */
    protected function fixHierarchy(SubmissionEntity $submission)
    {
        if (WordpressContentTypeHelper::CONTENT_TYPE_CATEGORY !== $submission->getContentType()) {
            return;
        }

        $originalEntity = $this->getContentHelper()->readSourceContent($submission);
        $parent = $originalEntity->getParent();

        if (IntegerParser::tryParseString($parent, $parent)) {

            $parentSubmission = $this->getTranslationHelper()->sendForTranslationSync(
                WordpressContentTypeHelper::CONTENT_TYPE_CATEGORY,
                $submission->getSourceBlogId(),
                $parent,
                $submission->getTargetBlogId()
            );

            $parentContent = $this->getContentHelper()->readSourceContent($parentSubmission);

            if (0 < IntegerParser::integerOrDefault($parentContent->getParent(), 0) ||
                0 === IntegerParser::integerOrDefault($parentSubmission->getTargetId(), 0)
            ) {
                do_action(ExportedAPI::ACTION_SMARTLING_DOWNLOAD_TRANSLATION, $parentSubmission);
            }
        }
    }
}