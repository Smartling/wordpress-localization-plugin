<?php

namespace Smartling\Helpers\MetaFieldProcessor;

use Psr\Log\LoggerInterface;
use Smartling\Submissions\SubmissionEntity;

/**
 * Class LocaleSpecificContentReplacementFilter
 * @package Smartling\Helpers\MetaFieldProcessor
 */
class LocaleSpecificContentReplacementFilter extends MetaFieldProcessorAbstract
{
    /**
     * @var array
     */
    private $replacementMap = [];

    /**
     * @return array
     */
    public function getReplacementMap()
    {
        return $this->replacementMap;
    }

    /**
     * @param array $replacementMap
     */
    public function setReplacementMap(array $replacementMap)
    {
        $this->replacementMap = $replacementMap;
    }

    /**
     * LocaleSpecificContentReplacementFilter constructor.
     *
     * @param LoggerInterface $logger
     * @param string          $fieldRegexp
     * @param array           $replacementMap
     */
    public function __construct(LoggerInterface $logger, $fieldRegexp, $replacementMap)
    {
        $this->setLogger($logger);
        $this->setFieldRegexp($fieldRegexp);
        $this->setReplacementMap($replacementMap);
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
        foreach ($this->getReplacementMap() as $original => $replacement) {
            if (is_array($replacement) && false !== strpos($value, $original) && array_key_exists($submission->getTargetLocale(), $replacement)) {
                $newSubString = $replacement[$submission->getTargetLocale()];
                $finalValue = str_replace($original, $newSubString, $value);
                $msg = vsprintf(
                    'Replacing \'%s\' with \'%s\' in source string \'%s\' and have \'%s\' in output',
                    [$original, $newSubString, $value, $finalValue]
                );
                $this->getLogger()->debug($msg);
                return $finalValue;
            }
        }
        return $value;
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
