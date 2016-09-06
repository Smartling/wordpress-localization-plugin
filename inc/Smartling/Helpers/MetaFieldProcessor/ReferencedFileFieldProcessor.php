<?php

namespace Smartling\Helpers\MetaFieldProcessor;

use Psr\Log\LoggerInterface;
use Smartling\Base\ExportedAPI;
use Smartling\Exception\SmartlingDataReadException;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\TranslationHelper;
use Smartling\Helpers\WordpressContentTypeHelper;
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
            $value = reset($value);
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
                    'Sending for translation referenced image id = \'%s\' related to submission = \'%s\'.',
                    [$value, $submission->getId(),]
                )
            );

            $attSubmission = $this->getTranslationHelper()->sendForTranslationSync(
                WordpressContentTypeHelper::CONTENT_TYPE_MEDIA_ATTACHMENT,
                $submission->getSourceBlogId(),
                $value,
                $submission->getTargetBlogId()
            );

            do_action(ExportedAPI::ACTION_SMARTLING_DOWNLOAD_TRANSLATION, $attSubmission);

            return $attSubmission->getTargetId();
        } catch (SmartlingDataReadException $e) {
            $message = vsprintf(
                'An error happened while processing referenced image with original value=%s. Keeping original value.',
                [
                    var_export($originalValue, true),
                ]
            );
            $this->getLogger()->error($message);

            return $originalValue;
        }
    }

    /**
     * @param string $fieldName
     * @param mixed  $value
     * @param array  $collectedFields
     *
     * @return mixed
     */
    public function processFieldPreTranslation($fieldName, $value, array $collectedFields)
    {
        return $value;
    }
}