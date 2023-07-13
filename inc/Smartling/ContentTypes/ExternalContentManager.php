<?php

namespace Smartling\ContentTypes;

use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Submissions\SubmissionEntity;

class ExternalContentManager
{
    use LoggerSafeTrait;

    /**
     * @var ContentTypePluggableInterface[] $handlers
     */
    private array $handlers = [];

    /** @noinspection PhpUnused, used in DI */
    public function addHandler(ContentTypePluggableInterface $handler): void
    {
        $this->handlers[] = $handler;
    }

    public function getExternalContent(array $source, SubmissionEntity $submission, bool $raw): array
    {
        foreach ($this->handlers as $handler) {
            switch ($handler->getSupportLevel($submission->getContentType(), $submission->getSourceId())) {
                case ContentTypePluggableInterface::SUPPORTED:
                    $this->getLogger()->debug("Determined support for {$handler->getPluginId()}, will try to get fields");
                    try {
                        $submission->assertHasSource();
                        $source[$handler->getPluginId()] = $handler->getContentFields($submission, $raw);
                    } catch (\Throwable $e) {
                        $this->getLogger()->notice('HandlerName="' . $handler->getPluginId() . '" got exception while trying to get external content: ' . $e->getMessage());
                    }
                    if ($handler instanceof ContentTypeModifyingInterface) {
                        try {
                            $source = $handler->alterContentFieldsForUpload($source);
                        } catch (\Throwable $e) {
                            $this->getLogger()->notice('HandlerName="' . $handler->getPluginId() . '" got exception while trying to alter content fields: ' . $e->getMessage());
                        }
                    }
                    break;
                case ContentTypePluggableInterface::VERSION_NOT_SUPPORTED:
                    if ($handler instanceof ContentTypeModifyingInterface) {
                        $this->getLogger()->warning("Detected not supported version for {$handler->getPluginId()}, will not include known problematic fields");
                        try {
                            $source = $handler->alterContentFieldsForUpload($source);
                        } catch (\Throwable $e) {
                            $this->getLogger()->warning('HandlerName="' . $handler->getPluginId() . '" got exception while trying to alter content fields: ' . $e->getMessage());
                        }
                    } else {
                        $this->getLogger()->debug("Detected not supported version for {$handler->getPluginId()}, no actions taken");
                    }
                    break;
            }
        }

        return $source;
    }

    public function getExternalContentTypes(): array
    {
        $result = [];
        foreach ($this->handlers as $handler) {
            $result[] = $handler->getExternalContentTypes();
        }

        return array_merge(...$result);
    }

    public function getExternalRelations(string $contentType, int $id): array
    {
        $result = [];
        foreach ($this->handlers as $handler) {
            if ($handler->getSupportLevel($contentType, $id) === ContentTypePluggableInterface::SUPPORTED) {
                $this->getLogger()->debug("Determined support for {$handler->getPluginId()}, will try to get related content");
                try {
                    $result = array_merge_recursive($result, $handler->getRelatedContent($contentType, $id));
                } catch (\Throwable $e) {
                    $this->getLogger()->notice('HandlerName="' . $handler->getPluginId() .
                        '" got errorClass="' . $e::class . '" while trying to get external related content: ' .
                        $e->getMessage());
                }
            }
        }

        return $result;
    }

    /**
     * @return ContentTypePluggableInterface[]
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }

    public function setExternalContent(array $original, array $translation, SubmissionEntity $submission): array
    {
        foreach ($this->handlers as $handler) {
            if ($handler->getSupportLevel($submission->getContentType(), $submission->getSourceId())) {
                $this->getLogger()->debug("Determined support for {$handler->getPluginId()}, will try to set fields");
                try {
                    $externalContent = $handler->setContentFields($original, $translation, $submission);
                    if ($externalContent !== null) {
                        $this->getLogger()->info('Content array modified by HandlerName="' . $handler->getPluginId() . '"');
                        $translation = $externalContent;
                    }
                } catch (\Throwable $e) {
                    $this->getLogger()->notice('HandlerName="' . $handler->getPluginId() . '" got exception while trying to set external content: ' . $e->getMessage());
                }
            }
        }

        return $translation;
    }
}
