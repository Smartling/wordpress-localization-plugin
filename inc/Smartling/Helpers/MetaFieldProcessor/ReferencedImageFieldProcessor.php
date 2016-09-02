<?php

namespace Smartling\Helpers\MetaFieldProcessor;

use Smartling\Base\ExportedAPI;
use Smartling\Exception\SmartlingDataReadException;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Submissions\SubmissionEntity;

/**
 * Class ReferencedImageFieldProcessor
 * @package Smartling\Helpers\MetaFieldProcessor
 */
class ReferencedImageFieldProcessor extends MetaFieldProcessorAbstract
{
    /**
     * @param SubmissionEntity $submission
     * @param mixed            $value
     *
     * @return mixed
     */
    public function processFieldValue(SubmissionEntity $submission, $value)
    {

        $originalValue = $value;

        if (is_array($value)) {
            $value = reset($value);
        }

        $value = (int)$value;

        if (0 >= $value) {
            $message = vsprintf(
                'Got bad reference number for submission id=%s metadata field=\'%s\' with value=\'%s\', expected integer > 0. Skipping.',
                [
                    $submission->getId(),
                    $this->getFieldName(),
                    var_export($originalValue, true),
                ]
            );
            $this->getLogger()->warning($message);

            return $originalValue;
        }

        try {

            $this->getLogger()->debug(
                vsprintf(
                    'Sending for translation referenced image id = \'%s\' related to submission = \'%s\'.',
                    [
                        $value,
                        $submission->getId(),
                    ]
                ));

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
}