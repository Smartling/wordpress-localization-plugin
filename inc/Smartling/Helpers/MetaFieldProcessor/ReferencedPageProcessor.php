<?php

namespace Smartling\Helpers\MetaFieldProcessor;

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

        if (0 < (int)$parent) {

            $parentSubmission = $this->getTranslationHelper()->sendForTranslationSync(
                WordpressContentTypeHelper::CONTENT_TYPE_PAGE,
                $submission->getSourceBlogId(),
                $parent,
                $submission->getTargetBlogId()
            );

            $parentContent = $this->getContentHelper()->readSourceContent($parentSubmission);

            if (0 < (int)$parentContent->getPostParent() || 0 === (int)$parentSubmission->getTargetId()) {
                do_action(ExportedAPI::ACTION_SMARTLING_DOWNLOAD_TRANSLATION, $parentSubmission);
            }
        }
    }
}