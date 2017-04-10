<?php

namespace Smartling\Helpers\MetaFieldProcessor;

use Psr\Log\LoggerInterface;
use Smartling\Exception\SmartlingDataReadException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\Parsers\IntegerParser;
use Smartling\Helpers\TranslationHelper;
use Smartling\Submissions\SubmissionEntity;

/**
 * Class ReferencedFileFieldProcessor
 * @package Smartling\Helpers\MetaFieldProcessor
 */
class ReferencedFileFieldProcessor extends MetaFieldProcessorAbstract
{
    /**
     * @var ContentHelper
     */
    private $contentHelper;

    /**
     * MetaFieldProcessorInterface constructor.
     *
     * @param LoggerInterface   $logger
     * @param TranslationHelper $translationHelper
     * @param string            $fieldRegexp
     */
    public function __construct(LoggerInterface $logger, TranslationHelper $translationHelper, $fieldRegexp)
    {
        $this->setLogger($logger);
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
     * @param string           $fieldName
     * @param mixed            $value
     *
     * @return mixed|string
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
            $this->getLogger()->debug(
                vsprintf(
                    'Sending for translation referenced image id = \'%s\' related to submission = \'%s\'.',
                    [$value, $submission->getId(),]
                )
            );

            $attSubmission = $this->getTranslationHelper()->tryPrepareRelatedContent(
                'attachment',
                $submission->getSourceBlogId(),
                $value,
                $submission->getTargetBlogId()
            );

            return $attSubmission->getTargetId();
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
                'An exception occurred while sending related item=%s, submission=%s for translation. Message:',
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