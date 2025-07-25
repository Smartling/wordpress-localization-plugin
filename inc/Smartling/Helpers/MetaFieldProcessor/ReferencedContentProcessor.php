<?php

namespace Smartling\Helpers\MetaFieldProcessor;

use Smartling\Exception\SmartlingDataReadException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\Parsers\IntegerParser;
use Smartling\Helpers\TranslationHelper;
use Smartling\Jobs\JobEntityWithBatchUid;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class ReferencedContentProcessor extends MetaFieldProcessorAbstract
{
    use LoggerSafeTrait;

    public function __construct(
        protected ContentHelper $contentHelper,
        private SubmissionManager $submissionManager,
        TranslationHelper $translationHelper,
        string $fieldRegexp,
        private string $contentType,
    ) {
        parent::__construct();
        $this->setTranslationHelper($translationHelper);
        $this->setFieldRegexp($fieldRegexp);
    }

    /**
     * @param string $fieldName
     * @param mixed $value
     */
    public function processFieldPostTranslation(SubmissionEntity $submission, $fieldName, $value): mixed
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

        if (is_int($value)) {
            $related = $this->submissionManager->findOne([
                SubmissionEntity::FIELD_CONTENT_TYPE => $this->contentType,
                SubmissionEntity::FIELD_SOURCE_BLOG_ID => $submission->getSourceBlogId(),
                SubmissionEntity::FIELD_SOURCE_ID => $value,
                SubmissionEntity::FIELD_TARGET_BLOG_ID => $submission->getTargetBlogId(),
            ]);
            if ($related !== null) {
                return $related->getTargetId();
            }
        }

        return $originalValue;
    }

    /**
     * @param string $fieldName
     * @param mixed $value
     */
    public function processFieldPreTranslation(
        SubmissionEntity $submission,
        $fieldName,
        $value,
        array $collectedFields,
        string $contentType = null,
    ): mixed {
        if ($contentType === null) {
            $contentType = $this->contentType;
        }
        try {
            $sourceBlogId = $submission->getSourceBlogId();
            $targetBlogId = $submission->getTargetBlogId();
            if ($this->getTranslationHelper()->isRelatedSubmissionCreationNeeded($contentType, $sourceBlogId, $value, $targetBlogId)) {
                $this->getLogger()->debug("Sending for translation referenced content id = '$value' related to submission = '{$submission->getId()}'.");

                $this->getTranslationHelper()->tryPrepareRelatedContent(
                    $contentType,
                    $sourceBlogId,
                    $value,
                    $targetBlogId,
                    JobEntityWithBatchUid::fromJob($submission->getJobInfo(), ''),
                    $submission->isCloned(),
                );
            }

            $this->getLogger()->debug("Skipped sending referenced content id = '$value' related to submission = '{$submission->getId()} due to manual relations handling");
        } catch (SmartlingDataReadException) {
            $message = vsprintf(
                'An error happened while processing referenced content with original value=%s. Keeping original value.',
                [
                    var_export($value, true),
                ]
            );
            $this->getLogger()->error($message);
        } catch (\Exception $e) {
            $message = vsprintf(
                'An exception occurred while sending related item=%s, submission=%s for translation. Message: %s',
                [
                    var_export($value, true),
                    $submission->getId(),
                    $e->getMessage(),
                ]
            );
            $this->getLogger()->error($message);
        }

        return $value;
    }
}
