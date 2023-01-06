<?php

namespace Smartling\Helpers\MetaFieldProcessor;

use Smartling\Base\ExportedAPI;
use Smartling\Exception\SmartlingInvalidFactoryArgumentException;
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

    public function getAcfTypeDetector(): AcfTypeDetector
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

    /** @noinspection PhpUnused, used in DI */
    public function registerProcessor(MetaFieldProcessorInterface $handler): void
    {
        $pattern = $handler->getFieldRegexp();
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
        $processorClass = get_class($processor);
        $result = $processor->processFieldPostTranslation($submission, $fieldName, $fieldValue);

        if ($result === $fieldValue) {
            $this->getLogger()->debug(sprintf(
                'Post Translation filter received submissionId="%s", field="%s", value="%s", processor="%s" left unchanged',
                $submission->getId(),
                $fieldName,
                var_export($fieldValue, true),
                $processorClass,
            ));
        } else {
            $this->getLogger()->debug(sprintf(
                'Post Translation filter received submissionId="%s", field="%s", value="%s", processor="%s", changed to %s',
                $submission->getId(),
                $fieldName,
                var_export($fieldValue, true),
                $processorClass,
                var_export($result, true),
            ));
        }

        return $result;
    }

    public function getProcessor(string $fieldName): MetaFieldProcessorInterface
    {
        $processor = $this->getHandler($fieldName);
        if ($processor instanceof DefaultMetaFieldProcessor && str_contains($fieldName, '/')) {
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

        $this->getLogger()->debug("Using processor " . get_class($processor) . " for $fieldName");
        return $processor;
    }

    public function getHandler(string $contentType): MetaFieldProcessorInterface
    {
        $registeredProcessors = $this->getCollection();

        foreach (array_keys($registeredProcessors) as $pattern) {
            if (preg_match(vsprintf('#%s#u', [$pattern]), $contentType)) {
                return $registeredProcessors[$pattern];
            }
        }

        if (true === $this->getAllowDefault() && null !== $this->getDefaultHandler()) {
            return $this->getDefaultHandler();
        }

        throw new SmartlingInvalidFactoryArgumentException(sprintf($this->message, $contentType, static::class));
    }

    private function tryGetAcfProcessor($fieldName, SubmissionEntity $submission, MetaFieldProcessorInterface $processor)
    {
        if (in_array(get_class($processor), [DefaultMetaFieldProcessor::class, PostContentProcessor::class], true)) {
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
    public function register(): void
    {
        add_filter(ExportedAPI::FILTER_SMARTLING_METADATA_FIELD_PROCESS, [$this, 'handlePostTranslationFields'], 10, 3);
        add_filter(ExportedAPI::FILTER_SMARTLING_METADATA_PROCESS_BEFORE_TRANSLATION, [$this,
                                                                                       'handlePreTranslationFields'], 10, 4);
    }
}