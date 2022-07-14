<?php

namespace Smartling\ContentTypes;

use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\PluginHelper;
use Smartling\Submissions\SubmissionEntity;

class ExternalContentManager
{
    use LoggerSafeTrait;
    /**
     * @var ContentTypePluggableInterface[] $handlers
     */
    private array $handlers;
    private PluginHelper $pluginHelper;

    public function __construct(PluginHelper $pluginHelper)
    {
        $this->pluginHelper = $pluginHelper;
    }

    /** @noinspection PhpUnused, used in DI */
    public function addHandler(ContentTypePluggableInterface $handler): void
    {
        $this->handlers[] = $handler;
    }

    public function getExternalContent(array $source, SubmissionEntity $submission, bool $raw): array
    {
        foreach ($this->handlers as $handler) {
            if ($this->pluginHelper->canHandleExternalContent($handler)) {
                try {
                    $source[$handler->getPluginId()] = $handler->getContentFields($submission, $raw);
                } catch (\Error $e) {
                    $this->getLogger()->notice('HandlerName="' . $handler->getPluginId() . '" got exception while trying to get external content: ' . $e->getMessage());
                }
                if ($handler instanceof ContentTypeModifyingInterface) {
                    try {
                        $source = $handler->alterContentFields($source);
                    } catch (\Error $e) {
                        $this->getLogger()->notice('HandlerName="' . $handler->getPluginId() . '" got exception while trying to alter content fields: ' . $e->getMessage());
                    }
                }
            }
        }

        return $source;
    }

    public function setExternalContent(array $content, SubmissionEntity $submission): array
    {
        foreach ($this->handlers as $handler) {
            if (array_key_exists($handler->getPluginId(), $content) && $this->pluginHelper->canHandleExternalContent($handler)) {
                try {
                    $externalContent = $handler->setContentFields($content[$handler->getPluginId()], $submission);
                    if ($externalContent !== null) {
                        $this->getLogger()->info('Content array modified by HandlerName="' . $handler->getPluginId() . '"');
                        $content = $externalContent;
                    }
                } catch (\Error $e) {
                    $this->getLogger()->notice('HandlerName="' . $handler->getPluginId() . '" got exception while trying to set external content: ' . $e->getMessage());
                }
            }
        }

        return $content;
    }
}
