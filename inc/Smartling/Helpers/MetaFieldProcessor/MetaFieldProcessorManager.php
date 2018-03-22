<?php

namespace Smartling\Helpers\MetaFieldProcessor;

use Smartling\Base\ExportedAPI;
use Smartling\Extensions\Acf\AcfTypeDetector;
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
     * @var array
     */
    private $collection = [];

    /**
     * @var AcfTypeDetector
     */
    private $acfTypeDetector;

    /**
     * @return AcfTypeDetector
     */
    public function getAcfTypeDetector()
    {
        return $this->acfTypeDetector;
    }

    /**
     * @param mixed $acfTypeDetector
     */
    public function setAcfTypeDetector(AcfTypeDetector $acfTypeDetector)
    {
        $this->acfTypeDetector = $acfTypeDetector;
    }

    protected function setCollection(array $collection = [])
    {
        $this->collection = $collection;
    }

    protected function getCollection()
    {
        return $this->collection;
    }

    protected function getCollectionKeys()
    {
        return array_keys($this->getCollection());
    }

    protected function collectionKeyExists($key)
    {
        return in_array($key, $this->getCollectionKeys(), true);
    }

    protected function insertIntoCollection($key, $value)
    {
        $_collection = $this->getCollection();
        $_collection[$key] = $value;
        $this->setCollection($_collection);
    }

    protected function removeFromCollection($key)
    {
        if ($this->collectionKeyExists($key)) {
            $_collection = $this->getCollection();
            unset($_collection[$key]);
            $this->setCollection($_collection);
        }
    }

    /**
     * MetaFieldProcessorManager constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->setAllowDefault(true);
    }

    /**
     * @param MetaFieldProcessorInterface $handler
     */
    public function registerProcessor(MetaFieldProcessorInterface $handler)
    {
        $pattern = $handler->getFieldRegexp();
        //$this->getLogger()->debug(vsprintf('Adding to collection processor for pattern: \'%s\'', [$pattern]));
        if ($this->collectionKeyExists($pattern)) {
            $this->removeFromCollection($pattern);
        }
        $this->insertIntoCollection($pattern, $handler);
        if (!$this->collectionKeyExists($pattern)) {
            $this->getLogger()->warning(vsprintf('FAILED Adding to collection processor for pattern: \'%s\'', [$pattern]));
        }
    }

    public function handlePostTranslationFields($fieldName, $fieldValue, SubmissionEntity $submission)
    {
        $processor = $this->getProcessor($fieldName);
        $processor = $this->tryGetAcfProcessor($fieldName, $submission, $processor);
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

        //$this->getLogger()->debug(vsprintf('Looking for match for \'%s\' in : %s',[$contentType, var_export($patterns, true)]));

        foreach ($patterns as $pattern) {
            if (preg_match(vsprintf('#%s#u', [$pattern]), $contentType)) {
                //$this->getLogger()->debug(vsprintf('Probing done successfully with pattern \'%s\' for \'%s\'...',[$pattern, $contentType]));
                return $registeredProcessors[$pattern];
            } else {
                //$this->getLogger()->debug(vsprintf('Probing failed with pattern \'%s\' for \'%s\'...',[$pattern, $contentType]));
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

    private function tryGetAcfProcessor($fieldName, SubmissionEntity $submission, MetaFieldProcessorInterface $processor)
    {
        if ('Smartling\Helpers\MetaFieldProcessor\DefaultMetaFieldProcessor' === get_class($processor)) {
            $_processor = $this->getAcfTypeDetector()->getProcessor($fieldName, $submission);
            if (false !== $_processor) {
                return $_processor;
            }
        }

        return $processor;
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
        $processor = $this->tryGetAcfProcessor($fieldName, $submission, $processor);
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