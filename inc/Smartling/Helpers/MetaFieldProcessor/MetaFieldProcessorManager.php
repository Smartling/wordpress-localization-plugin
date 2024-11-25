<?php

namespace Smartling\Helpers\MetaFieldProcessor;

use Smartling\Base\ExportedAPI;
use Smartling\Exception\SmartlingInvalidFactoryArgumentException;
use Smartling\Extensions\Acf\AcfTypeDetector;
use Smartling\Processors\SmartlingFactoryAbstract;
use Smartling\Submissions\SubmissionEntity;
use Smartling\WP\WPHookInterface;

class MetaFieldProcessorManager extends SmartlingFactoryAbstract implements WPHookInterface
{
    private AcfTypeDetector $acfTypeDetector;

    public function getAcfTypeDetector(): AcfTypeDetector
    {
        return $this->acfTypeDetector;
    }

    public function __construct(AcfTypeDetector $acfTypeDetector, bool $allowDefault = false, ?object $defaultHandler = null)
    {
        parent::__construct($allowDefault, $defaultHandler);
        $this->acfTypeDetector = $acfTypeDetector;
    }

    /** @noinspection PhpUnused, used in DI */
    public function registerProcessor(MetaFieldProcessorInterface $handler): void
    {
        $this->collection[$handler->getFieldRegexp()] = $handler;
    }

    public function handlePostTranslationFields($fieldName, $fieldValue, SubmissionEntity $submission): mixed
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
        $registeredProcessors = $this->collection;

        foreach (array_keys($registeredProcessors) as $pattern) {
            if (preg_match(vsprintf('#%s#u', [$pattern]), $contentType)) {
                return $registeredProcessors[$pattern];
            }
        }

        if ($this->defaultHandler instanceof MetaFieldProcessorInterface) {
            return $this->defaultHandler;
        }

        throw new SmartlingInvalidFactoryArgumentException(sprintf($this->message, $contentType, static::class));
    }

    private function tryGetAcfProcessor($fieldName, SubmissionEntity $submission, MetaFieldProcessorInterface $processor): MetaFieldProcessorInterface
    {
        if (in_array(get_class($processor), [DefaultMetaFieldProcessor::class, PostContentProcessor::class], true)) {
            return $this->getAcfTypeDetector()->getProcessor($fieldName, $submission) ?? $processor;
        }

        return $processor;
    }

    public function handlePreTranslationFields(SubmissionEntity $submission, string $fieldName, mixed $fieldValue, array $collectedValues = []): mixed
    {
        $processor = $this->tryGetAcfProcessor($fieldName, $submission, $this->getProcessor($fieldName));
        $result = $processor->processFieldPreTranslation($submission, $fieldName, $fieldValue, $collectedValues);

        if (!$processor instanceof DefaultMetaFieldProcessor) {
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
