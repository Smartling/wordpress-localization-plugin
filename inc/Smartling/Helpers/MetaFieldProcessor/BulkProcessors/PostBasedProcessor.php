<?php

namespace Smartling\Helpers\MetaFieldProcessor\BulkProcessors;

use Smartling\Helpers\MetaFieldProcessor\ReferencedPostBasedContentProcessor;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Vendor\Symfony\Component\Config\Definition\Exception\Exception;

class PostBasedProcessor extends ReferencedPostBasedContentProcessor
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

            foreach ($deserializedValue as & $_value) {
                try {
                    $_value = parent::processFieldPostTranslation($submission, $fieldName, $_value);
                } catch (Exception $e) {
                    $this->getLogger()->error(sprintf('Error filtering fieldName="%s", value="%s", processorClass="%s", errorMessage="%s"',
                        $fieldName, var_export(addslashes($value), true), get_class($this), addslashes($e->getMessage()),
                    ));
                }
            }

            $serializedValue = $this->getSerializer()->serialize($deserializedValue);
        } catch (\Exception $e) {
            $this->getLogger()
                ->error(sprintf('Error filtering fieldName="%s", value="%s", processorClass="%s", errorMessage="%s". Using original value.',
                    $fieldName, var_export($value, true), get_class($this), $e->getMessage(),
                ));

            return $originalValue;
        }

        return $serializedValue;
    }
}