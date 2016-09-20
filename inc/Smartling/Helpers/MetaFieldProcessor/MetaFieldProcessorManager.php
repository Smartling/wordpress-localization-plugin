<?php

namespace Smartling\Helpers\MetaFieldProcessor;

use Psr\Log\LoggerInterface;
use Smartling\Base\ExportedAPI;
use Smartling\Processors\SmartlingFactoryAbstract;
use Smartling\Submissions\SubmissionEntity;
use Smartling\WP\WPHookInterface;

/**
 * Class MetaFieldProcessorManager
 * @package Smartling\Helpers\MetaFieldProcessor
 */
class MetaFieldProcessorManager extends SmartlingFactoryAbstract implements WPHookInterface
{
    /**
     * MetaFieldProcessorManager constructor.
     *
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);
        $this->setAllowDefault(true);
    }

    /**
     * @param MetaFieldProcessorInterface $handler
     */
    public function registerProcessor(MetaFieldProcessorInterface $handler)
    {
        parent::registerHandler($handler->getFieldRegexp(), $handler);
    }

    public function handlePostTranslationFields($fieldName, $fieldValue, SubmissionEntity $submission)
    {

        $processor = $this->getProcessor($fieldName);

        $result = $processor->processFieldPostTranslation($submission, $fieldName, $fieldValue);

        $this->getLogger()->debug(
            vsprintf(
                'Post Translation filter received field=\'%s\' with value=%s for submission id=\'%s\' that was processed by %s processor and received %s on output.',
                [
                    $fieldName,
                    var_export($fieldValue, true),
                    $submission->getId(),
                    get_class($processor),
                    var_export($result, true),
                ]
            ));

        return $result;
    }

    /**
     * @param $fieldName
     *
     * @return MetaFieldProcessorInterface
     */
    public function getProcessor($fieldName)
    {
        $processor = $this->getHandler($fieldName);
        if ($processor instanceof MetaFieldProcessorInterface) {
            if ($processor instanceof DefaultMetaFieldProcessor && false !== strpos($fieldName, '/')) {
                $parts = explode('/', $fieldName);
                foreach ($parts as $fieldNamePart) {
                    if (in_array($fieldNamePart, ['entity', 'meta'], true)) {
                        continue;
                    }
                    $partProcessor = $this->getProcessor($fieldNamePart);
                    if (!($partProcessor instanceof DefaultMetaFieldProcessor)) {
                        $processor = $partProcessor;
                        break;
                    }
                }
            }
        } else {
            $this->getLogger()->warning(
                vsprintf(
                    'Found strange processor \'%s\' for field name \'%s\'.',
                    [
                        get_class($processor),
                        $fieldName,
                    ]
                )
            );
        }

        return $processor;
    }

    /**
     * @param MetaFieldProcessorInterface
     *
     * @return object
     */
    public function getHandler($contentType)
    {
        $registeredProcessors = $this->getCollection();

        $patterns = array_keys($registeredProcessors);

        foreach ($patterns as $pattern) {
            if (preg_match(vsprintf('/%s/iu', [$pattern]), $contentType)) {
                return $registeredProcessors[$pattern];
            }
        }

        if (true === $this->getAllowDefault() && null !== $this->getDefaultHandler()) {
            return $this->getDefaultHandler();
        } else {
            $message = vsprintf($this->message, [$contentType, get_called_class()]);
            $this->getLogger()->error($message);
            throw new SmartlingInvalidFactoryArgumentException($message);
        }
    }

    /**
     * @param SubmissionEntity $submission
     * @param string           $fieldName
     * @param mixed            $fieldValue
     * @param array            $collectedValues
     *
     * @return mixed
     */
    public function handlePreTranslationFields(SubmissionEntity $submission, $fieldName, $fieldValue, array $collectedValues = [])
    {
        $processor = $this->getProcessor($fieldName);

        $result = $processor->processFieldPreTranslation($submission, $fieldName, $fieldValue, $collectedValues);

        if ($processor instanceof DefaultMetaFieldProcessor) {
            /**
             * Skip logging for Default Processor
             */
            /*
             * $this->getLogger()->debug(
                vsprintf(
                    'Pre translation filter received field=\'%s\' with value=%s. that was processed by %s processor.',
                    [
                        $fieldName,
                        var_export($fieldValue, true),
                        get_class($processor),
                    ]
                ));
            */
        } else {
            $this->getLogger()->debug(
                vsprintf(
                    'Pre translation filter received field=\'%s\' with value=%s. that was processed by %s processor and received %s on output.',
                    [
                        $fieldName,
                        var_export($fieldValue, true),
                        get_class($processor),
                        var_export($result, true),
                    ]
                )
            );
        }
        return $result;
    }

    /**
     * Registers wp hook handlers. Invoked by wordpress.
     * @return void
     */
    public function register()
    {
        add_filter(ExportedAPI::FILTER_SMARTLING_METADATA_FIELD_PROCESS, [$this, 'handlePostTranslationFields'], 10, 3);
        add_filter(ExportedAPI::FILTER_SMARTLING_METADATA_PROCESS_BEFORE_TRANSLATION, [$this,
                                                                                       'handlePreTranslationFields'], 10, 4);
    }
}