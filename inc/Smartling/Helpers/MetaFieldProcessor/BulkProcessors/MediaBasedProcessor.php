<?php

namespace Smartling\Helpers\MetaFieldProcessor\BulkProcessors;

use Smartling\Bootstrap;
use Smartling\Helpers\MetaFieldProcessor\ReferencedFileFieldProcessor;
use Smartling\Submissions\SubmissionEntity;

/**
 * Class MediaBasedProcessor
 * @package Smartling\Helpers\MetaFieldProcessor\BulkProcessors
 */
class MediaBasedProcessor extends ReferencedFileFieldProcessor
{
    use SerializerTrait;

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

        try {
            $deserializedValue = $this->getSerializer()->unserialize($value);
            if (!is_array($deserializedValue)){
                Bootstrap::DebugPrint([1,$deserializedValue,$fieldName], true);
            }
            if (0===count($deserializedValue)){
                Bootstrap::DebugPrint([1,$deserializedValue,$fieldName], true);
            }

            foreach ($deserializedValue as & $_value) {
                try {
                    $_value = parent::processFieldPostTranslation($submission, $fieldName, $_value);
                } catch (Exception $e) {
                    $this->getLogger()->error(vsprintf('Error filtering field \'%s\' with value \'%s\' by \'%s\'', [
                        $fieldName, var_export($value, true), get_class($this),
                    ]));
                }

            }

            $serializedValue = $this->getSerializer()->serialize($deserializedValue);
        } catch (\Exception $e) {
            $this->getLogger()
                ->error(vsprintf('Error filtering field \'%s\' with value \'%s\' by \'%s\'. Using original value.', [
                    $fieldName, var_export($value, true), get_class($this),
                ]));

            return $originalValue;
        }

        return $serializedValue;
    }
}