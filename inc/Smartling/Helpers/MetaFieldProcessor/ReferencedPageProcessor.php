<?php

namespace Smartling\Helpers\MetaFieldProcessor;

use Smartling\Helpers\Parsers\IntegerParser;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Submissions\SubmissionEntity;

/**
 * Class ReferencedPageProcessor
 * @package Smartling\Helpers\MetaFieldProcessor
 */
class ReferencedPageProcessor extends ReferencedContentProcessor
{
    /**
     * @param SubmissionEntity $submission
     * @param string           $fieldName
     * @param mixed            $value
     *
     * @return mixed
     */
    public function processFieldPostTranslation(SubmissionEntity $submission, $fieldName, $value)
    {
        $newId = parent::processFieldPostTranslation($submission, $fieldName, $value);
        $this->fixHierarchy($submission);

        return $newId;
    }

    /**
     * @param SubmissionEntity $submission
     *
     * @throws \Smartling\Exception\SmartlingDataReadException
     */
    protected function fixHierarchy(SubmissionEntity $submission)
    {
        if (WordpressContentTypeHelper::CONTENT_TYPE_PAGE !== $submission->getContentType()) {
            return;
        }

        $originalEntity = $this->getContentHelper()->readSourceContent($submission);
        $parent = $originalEntity->getPostParent();

        if (IntegerParser::tryParseString($parent, $parent)) {

            $parentSubmission = $this->getTranslationHelper()->sendForTranslationSync(
                WordpressContentTypeHelper::CONTENT_TYPE_PAGE,
                $submission->getSourceBlogId(),
                $parent,
                $submission->getTargetBlogId()
            );

            $parentContent = $this->getContentHelper()->readSourceContent($parentSubmission);

            if (0 < IntegerParser::integerOrDefault($parentContent->getPostParent(), 0) ||
                0 === IntegerParser::integerOrDefault($parentSubmission->getTargetId(), 0)
            ) {
                do_action(ExportedAPI::ACTION_SMARTLING_DOWNLOAD_TRANSLATION, $parentSubmission);
            }
        }
    }
}