<?php

namespace Smartling\Helpers\MetaFieldProcessor;

use Psr\Log\LoggerInterface;
use Smartling\Helpers\ContentHelper;
use Smartling\Submissions\SubmissionEntity;

/**
 * Class CloneValueFieldProcessor
 * @package Smartling\Helpers\MetaFieldProcessor
 */
class CloneValueFieldProcessor extends MetaFieldProcessorAbstract
{

    /**
     * @var ContentHelper
     */
    private $contentHelper;

    /**
     * BaseFieldProcessorAbstract constructor.
     *
     * @param string          $fieldRegexp
     * @param ContentHelper   $contentHelper
     * @param LoggerInterface $logger
     */
    public function __construct($fieldRegexp, ContentHelper $contentHelper, LoggerInterface $logger)
    {
        $this->setFieldRegexp($fieldRegexp);
        $this->setContentHelper($contentHelper);
        $this->setLogger($logger);
    }

    /**
     * @param SubmissionEntity $submission
     * @param string           $fieldName
     * @param mixed            $value
     *
     * @return array|mixed
     */
    public function processFieldPostTranslation(SubmissionEntity $submission, $fieldName, $value)
    {
        $originalMetadata = $this->getContentHelper()->readSourceMetadata($submission);

        if (array_key_exists($fieldName, $originalMetadata)) {
            $originalValue = $originalMetadata[$fieldName];

            if (is_array($originalValue) && 1 === count($originalValue)) {
                $originalValue = reset($originalValue);
                if (is_array($value)) {
                    // we should deserialize source
                    $originalValue = maybe_unserialize($originalValue);
                }
            }
            $value = $originalValue;
        } else {
            $this->getLogger()->warning(
                vsprintf(
                    'Cannot clone value of metadata=\'%s\' for submission=\'%s\'. Keeping value=%s',
                    [$fieldName, $submission->getId(), var_export($value, true),]
                )
            );
        }

        return $value;
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
     * @param SubmissionEntity $submission
     * @param string           $fieldName
     * @param mixed            $value
     * @param array            $collectedFields
     *
     * @return mixed or empty string (to skip translation)
     */
    public function processFieldPreTranslation(SubmissionEntity $submission, $fieldName, $value, array $collectedFields)
    {
        return '';
    }
}