<?php

namespace Smartling\Helpers\MetaFieldProcessor;

use Smartling\Helpers\ArrayHelper;
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
     */
    public function __construct($fieldRegexp, ContentHelper $contentHelper)
    {
        $this->setFieldRegexp($fieldRegexp);
        $this->setContentHelper($contentHelper);
        parent::__construct();
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
        $metaFieldName = str_replace('meta/','', $fieldName);

        if (array_key_exists($metaFieldName, $originalMetadata)) {
            $originalValue = $originalMetadata[$metaFieldName];

            if (is_array($originalValue) && 1 === count($originalValue)) {
                $originalValue = ArrayHelper::first($originalValue);
                if (is_array($value)) {
                    // we should deserialize source
                    $originalValue = maybe_unserialize($originalValue);
                }
            }
            $value = $originalValue;
        } else {
            $this->getLogger()->debug(
                vsprintf(
                    'Metadata="%s" for submissionId="%s" not found in source metadata. Keeping value="%s"',
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