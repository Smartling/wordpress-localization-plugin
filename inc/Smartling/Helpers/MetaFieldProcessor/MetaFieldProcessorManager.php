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
        $this->getLogger()->debug(
            vsprintf(
                'Post Translation filter received field=\'%s\' with value=%s for submission id=\'%s\'.',
                [
                    $fieldName,
                    var_export($fieldValue, true),
                    $submission->getId(),
                ]
            ));
        $processor = $this->getProcessor($fieldName);

        return $processor->processFieldPostTranslation($submission, $fieldName, $fieldValue);
    }

    /**
     * @param $fieldName
     *
     * @return MetaFieldProcessorInterface
     */
    public function getProcessor($fieldName)
    {
        $this->getLogger()->debug(vsprintf('Got \'%s\' field name. Looking for processor.', [$fieldName]));
        $processor = $this->getHandler($fieldName);
        if ($processor instanceof MetaFieldProcessorInterface) {
            if ($processor instanceof DefaultMetaFieldProcessor && false !== strpos($fieldName, '/')) {
                $parts = explode('/', $fieldName);
                foreach ($parts as $fieldNamePart) {
                    $partProcessor = $this->getProcessor($fieldNamePart);
                    if (!($partProcessor instanceof DefaultMetaFieldProcessor)) {
                        $this->getLogger()->debug(
                            vsprintf(
                                'Found processor \'%s\' for part \'%s\' of field name \'%s\'.',
                                [
                                    get_class($partProcessor),
                                    $fieldNamePart,
                                    $fieldName,
                                ]
                            )
                        );
                        $processor = $partProcessor;
                        break;
                    }
                }
            } else {
                $this->getLogger()->debug(
                    vsprintf(
                        'Found processor \'%s\' for field name \'%s\'.',
                        [
                            get_class($processor),
                            $fieldName,
                        ]
                    )
                );
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

    public function handlePreTranslationFields($fieldName, $fieldValue, array $collectedValues = [])
    {
        $this->getLogger()->debug(vsprintf('Pre translation filter received field=\'%s\' with value=%s.',
                                           [$fieldName, var_export($fieldValue, true)]));
        $processor = $this->getProcessor($fieldName);

        return $processor->processFieldPreTranslation($fieldName, $fieldValue, $collectedValues);
    }

    /**
     * Registers wp hook handlers. Invoked by wordpress.
     * @return void
     */
    public function register()
    {
        $filters = [
            ExportedAPI::FILTER_SMARTLING_METADATA_FIELD_PROCESS              => 'handlePostTranslationFields',
            ExportedAPI::FILTER_SMARTLING_METADATA_PROCESS_BEFORE_TRANSLATION => 'handlePreTranslationFields',
        ];

        foreach ($filters as $filterName => $handlerMethod) {
            add_filter($filterName, [$this, $handlerMethod], 10, 3);
        }
    }
}