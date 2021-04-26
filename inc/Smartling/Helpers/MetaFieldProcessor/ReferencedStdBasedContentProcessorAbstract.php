<?php

namespace Smartling\Helpers\MetaFieldProcessor;

use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\Parsers\IntegerParser;
use Smartling\Helpers\TranslationHelper;
use Smartling\Submissions\SubmissionEntity;

abstract class ReferencedStdBasedContentProcessorAbstract extends MetaFieldProcessorAbstract
{
    /**
     * @var ContentHelper
     */
    private $contentHelper;

    /**
     * @return ContentHelper
     */
    public function getContentHelper()
    {
        return $this->contentHelper;
    }

    /**
     * @param ContentHelper $contentHelper
     */
    public function setContentHelper(ContentHelper $contentHelper)
    {
        $this->contentHelper = $contentHelper;
    }

    /**
     * ReferencedPostBasedContentProcessor constructor.
     *
     * @param TranslationHelper $translationHelper
     * @param string            $fieldRegexp
     */
    public function __construct(TranslationHelper $translationHelper, $fieldRegexp)
    {
        parent::__construct();
        $this->setTranslationHelper($translationHelper);
        $this->setFieldRegexp($fieldRegexp);
    }

    /**
     * @param int $blogId
     * @param int $contentId
     *
     * @return string mixed
     */
    abstract protected function detectRealContentType($blogId, $contentId);

    /**
     * @param SubmissionEntity $submission
     * @param string           $fieldName
     * @param mixed            $value
     *
     * @return mixed
     */
    public function processFieldPostTranslation(SubmissionEntity $submission, $fieldName, $value)
    {
        $originalValue = $value;

        if (is_array($value)) {
            $value = ArrayHelper::first($value);
        }

        if (!IntegerParser::tryParseString($value, $value)) {
            $message = vsprintf(
                'Got bad reference number for submission id=%s metadata field=\'%s\' with value=\'%s\', expected integer > 0. Skipping.',
                [$submission->getId(), $fieldName, var_export($originalValue, true),]
            );
            $this->getLogger()->warning($message);

            return $originalValue;
        }

        if (0 === $value) {
            return $value;
        }

        try {
            $sourceBlogId = $submission->getSourceBlogId();
            $contentType = $this->detectRealContentType($sourceBlogId, $value);
            $targetBlogId = $submission->getTargetBlogId();
            if ($this->getTranslationHelper()->isRelatedSubmissionCreationNeeded($contentType, $sourceBlogId, $value, $targetBlogId)) {
                $this->getLogger()->debug("Sending for translation referenced content id = '$value' related to submission = '{$submission->getId()}'.");

                if ($this->getContentHelper()->checkEntityExists($sourceBlogId, $contentType, $value)) {
                    return $this->getTranslationHelper()->tryPrepareRelatedContent(
                        $contentType,
                        $sourceBlogId,
                        $value,
                        $targetBlogId,
                        $submission->getJobInfoWithBatchUid(),
                        (1 === $submission->getIsCloned())
                    )->getTargetId();
                }

                $this->getLogger()->debug("Couldn't identify content type for id='$value', blog='$sourceBlogId'. Keeping existing value '$value'.");
            } else {
                $this->getLogger()->debug("Skip sending for translation referenced content id = '$value' related to submission = '{$submission->getId()}' due to manual relations handling");
            }

            return $value;
        } catch (\Exception $e) {
            $message = vsprintf(
                'An exception occurred while sending related item=%s, submission=%s for translation. Message: %s',
                [
                    var_export($originalValue, true),
                    $submission->getId(),
                    $e->getMessage(),
                ]
            );
            $this->getLogger()->error($message);
        }

        return $originalValue;
    }

    /**
     * @param SubmissionEntity $submission
     * @param string           $fieldName
     * @param mixed            $value
     * @param array            $collectedFields
     *
     * @return mixed or empty string (to skip translation)
     */
    public function processFieldPreTranslation(SubmissionEntity $submission, $fieldName, $value, array $collectedFields)
    {
        return $this->processFieldPostTranslation($submission, $fieldName, $value);
    }
}
