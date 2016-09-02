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
        parent::registerHandler($handler->getFieldName(), $handler);
    }

    /**
     * @param $fieldName
     *
     * @return MetaFieldProcessorInterface
     */
    public function getProcessor($fieldName)
    {
        $this->getLogger()->debug(vsprintf('Got \'%s\' field name. Looking for processor.', [$fieldName]));

        $processor = clone parent::getHandler($fieldName);

        if ($processor instanceof MetaFieldProcessorInterface) {
            $this->getLogger()->debug(vsprintf('Found processor \'%s\' for field name \'%s\'.', [get_class($processor),
                                                                                                 $fieldName]));
        } else {
            $this->getLogger()
                ->warning(vsprintf('Found strange processor \'%s\' for field name \'%s\'.', [get_class($processor),
                                                                                             $fieldName]));
        }

        return $processor;
    }

    public function processorFilterHandler($fieldName, $fieldValue, SubmissionEntity $submission)
    {
        $this->getLogger()->debug(
            'Received field=\'%s\' with value=%s for submission id=\'%s\'.',
            [
                $fieldName,
                var_export($fieldValue, true),
                $submission->getId(),
            ]
        );
        $processor = $this->getProcessor($fieldName);

        return $processor->processFieldValue($submission, $fieldValue);
    }

    /**
     * Registers wp hook handlers. Invoked by wordpress.
     * @return void
     */
    public function register()
    {
        add_filter(ExportedAPI::FILTER_SMARTLING_METADATA_FIELD_PROCESS, [$this, 'processorFilterHandler'], 10, 3);
    }
}