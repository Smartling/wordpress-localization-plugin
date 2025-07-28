<?php

namespace Smartling\Helpers\MetaFieldProcessor\BulkProcessors;

use Smartling\Helpers\MetaFieldProcessor\ReferencedContentProcessor;
use Smartling\Submissions\SubmissionEntity;

/**
 * Class CustomTypeProcessor
 * @package Smartling\Helpers\MetaFieldProcessor\BulkProcessors
 */
class CustomTypeProcessor extends ReferencedContentProcessor
{

    use SerializerTrait;

    /**
     * @param string $fieldName
     * @param mixed $value
     */
    public function processFieldPostTranslation(SubmissionEntity $submission, $fieldName, $value): mixed
    {
        $originalValue = $value;

        try {
            $deserializedValue = $this->getSerializer()->unserialize($value);

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
