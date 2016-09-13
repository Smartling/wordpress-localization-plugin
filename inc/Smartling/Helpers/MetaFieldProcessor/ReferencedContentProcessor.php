<?php

namespace Smartling\Helpers\MetaFieldProcessor;

use Psr\Log\LoggerInterface;
use Smartling\Base\ExportedAPI;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\TranslationHelper;
use Smartling\Submissions\SubmissionEntity;

/**
 * Class ReferencedContentProcessor
 * @package Smartling\Helpers\MetaFieldProcessor
 */
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
     * @param LoggerInterface   $logger
     * @param TranslationHelper $translationHelper
     * @param string            $fieldRegexp
     * @param string            $contentType
     */
    public function __construct(LoggerInterface $logger, TranslationHelper $translationHelper, $fieldRegexp, $contentType)
    {
        $this->setLogger($logger);
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
        if ($this->getContentType() !== $submission->getContentType()) {
            return $value;
        }

        $originalValue = $value;

        if (is_array($value)) {
            $keys = array_keys($value);
            $value = $value[$keys[0]];
        }

        $value = (int)$value;

        if (0 >= $value) {
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
                    'Sending for translation referenced content id = \'%s\' related to submission = \'%s\'.',
                    [$value, $submission->getId()]
                )
            );

            // trying to detect
            $attSubmission = $this->getTranslationHelper()->sendForTranslationSync(
                $this->getContentType(),
                $submission->getSourceBlogId(),
                $value,
                $submission->getTargetBlogId()
            );

            if (0 === (int)$attSubmission->getTargetId()) {
                do_action(ExportedAPI::ACTION_SMARTLING_DOWNLOAD_TRANSLATION, $attSubmission);
            }

            return $attSubmission->getTargetId();
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
        return $value;
    }
}