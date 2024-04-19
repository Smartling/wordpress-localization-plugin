<?php

namespace Smartling\ContentTypes;

use Smartling\Extensions\Pluggable;
use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\SiteHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\Submission;

class ExternalContentManager
{
    use LoggerSafeTrait;

    public function __construct(
        private FieldsFilterHelper $fieldsFilterHelper,
        private SiteHelper $siteHelper,
    ) {
    }

    /**
     * @var ContentTypePluggableInterface[] $handlers
     */
    private array $handlers = [];

    public function addHandler(ContentTypePluggableInterface $handler): void
    {
        $this->handlers[] = $handler;
    }

    public function getExternalContent(array $source, SubmissionEntity $submission, bool $raw): array
    {
        return $this->siteHelper->withBlog($submission->getSourceBlogId(), function () use ($raw, $source, $submission) {
            foreach ($this->handlers as $handler) {
                if ($handler->getSupportLevel($submission->getContentType(), $submission->getSourceId()) === Pluggable::SUPPORTED) {
                    $this->getLogger()->debug("Determined support for {$handler->getPluginId()}, will try to get fields");
                    try {
                        $submission->assertHasSource();
                        $source[$handler->getPluginId()] = $handler->getContentFields($submission, $raw);
                    } catch (\Throwable $e) {
                        $this->getLogger()->notice('HandlerName="' . $handler->getPluginId() . '" got exception while trying to get external content: ' . $e->getMessage());
                    }
                }
                if ($handler instanceof ContentTypeModifyingInterface) {
                    try {
                        $previousCount = count($this->fieldsFilterHelper->flattenArray($source));
                        $source = $handler->removeUntranslatableFieldsForUpload($source, $submission);
                        $count = count($this->fieldsFilterHelper->flattenArray($source));
                        if ($previousCount !== $count) {
                            $this->getLogger()->info('HandlerName="' . $handler->getPluginId() . '" altered content fields for upload, previousCount=' . $previousCount . ', count=' . $count);
                        }
                    } catch (\Throwable $e) {
                        $this->getLogger()->warning('HandlerName="' . $handler->getPluginId() . '" got exception while trying to alter content fields: ' . $e->getMessage());
                    }
                }
            }

            return $source;
        });
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
            if ($handler->getSupportLevel($contentType, $id) === Pluggable::SUPPORTED) {
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

    public function setExternalContent(array $original, array $translation, Submission $submission): array
    {
        foreach ($this->handlers as $handler) {
            if ($handler->getSupportLevel($submission->getContentType(), $submission->getSourceId()) === Pluggable::SUPPORTED) {
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
            } else {
                $this->getLogger()->debug("No support for {$handler->getPluginId()} detected");
            }
        }

        return $translation;
    }
}
