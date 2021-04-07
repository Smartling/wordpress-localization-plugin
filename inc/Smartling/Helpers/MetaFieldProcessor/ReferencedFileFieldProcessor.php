<?php

namespace Smartling\Helpers\MetaFieldProcessor;

use Smartling\Exception\SmartlingDataReadException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\Parsers\IntegerParser;
use Smartling\Helpers\TranslationHelper;
use Smartling\Submissions\SubmissionEntity;

class ReferencedFileFieldProcessor extends MetaFieldProcessorAbstract
{
    /**
     * @var ContentHelper
     */
    private $contentHelper;

    /**
     * MetaFieldProcessorInterface constructor.
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
     * @return mixed
     */
    public function getContentHelper()
    {
        return $this->contentHelper;
    }

    /**
     * @param mixed $contentHelper
     */
    public function setContentHelper($contentHelper)
    {
        $this->contentHelper = $contentHelper;
    }

    /**
     * @param SubmissionEntity $submission
     * @param string $fieldName
     * @param mixed $value
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

        try {
            $contentType = 'attachment';
            $sourceBlogId = $submission->getSourceBlogId();
            $targetBlogId = $submission->getTargetBlogId();
            if ($this->getTranslationHelper()->isRelatedSubmissionCreationNeeded($contentType, $sourceBlogId, $value, $targetBlogId)) {
                $this->getLogger()->debug("Sending for translation referenced image id = '$value' related to submission = '{$submission->getId()}'.");

                return $this->getTranslationHelper()->tryPrepareRelatedContent(
                    $contentType,
                    $sourceBlogId,
                    $value,
                    $targetBlogId,
                    $submission->getJobInfo(),
                    (1 === $submission->getIsCloned())
                )->getTargetId();
            }

            $this->getLogger()->debug("Skip sending for translation referenced image id = '$value' related to submission = '{$submission->getId()}'.");
        } catch (SmartlingDataReadException $e) {
            $message = vsprintf(
                'An error happened while processing referenced image with original value=%s. Keeping original value.',
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
