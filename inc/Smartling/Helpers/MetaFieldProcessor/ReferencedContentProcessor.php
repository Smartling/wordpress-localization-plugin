<?php

namespace Smartling\Helpers\MetaFieldProcessor;

use Smartling\Exception\SmartlingDataReadException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\Parsers\IntegerParser;
use Smartling\Helpers\TranslationHelper;
use Smartling\JobInfo;
use Smartling\Submissions\SubmissionEntity;

class ReferencedContentProcessor extends MetaFieldProcessorAbstract
{
    /**
     * @var ContentHelper
     */
    private $contentHelper;

    /**
     * @var string
     */
    private $contentType;

    /**
     * @return string
     */
    public function getContentType()
    {
        return $this->contentType;
    }

    /**
     * @param string $contentType
     */
    public function setContentType($contentType)
    {
        $this->contentType = $contentType;
    }

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
    public function setContentHelper($contentHelper)
    {
        $this->contentHelper = $contentHelper;
    }

    /**
     * MetaFieldProcessorInterface constructor.
     *
     * @param TranslationHelper $translationHelper
     * @param string            $fieldRegexp
     * @param string            $contentType Expected content type in the field
     */
    public function __construct(TranslationHelper $translationHelper, $fieldRegexp, $contentType)
    {
        parent::__construct();
        $this->setTranslationHelper($translationHelper);
        $this->setFieldRegexp($fieldRegexp);
        $this->setContentType($contentType);
    }

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
            $contentType = $this->getContentType();
            $sourceBlogId = $submission->getSourceBlogId();
            $targetBlogId = $submission->getTargetBlogId();
            if ($this->getTranslationHelper()->isRelatedSubmissionCreationNeeded($contentType, $sourceBlogId, $value, $targetBlogId)) {
                $this->getLogger()->debug("Sending for translation referenced content id = '$value' related to submission = '{$submission->getId()}'.");

                return $this->getTranslationHelper()->tryPrepareRelatedContent(
                    $contentType,
                    $sourceBlogId,
                    $value,
                    $targetBlogId,
                    $submission->getJobInfo(),
                    (1 === $submission->getIsCloned())
                )->getTargetId();
            }

            $this->getLogger()->debug("Skipped sending referenced content id = '$value' related to submission = '{$submission->getId()} due to manual relations handling");
        } catch (SmartlingDataReadException $e) {
            $message = vsprintf(
                'An error happened while processing referenced content with original value=%s. Keeping original value.',
                [
                    var_export($originalValue, true),
                ]
            );
            $this->getLogger()->error($message);
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
