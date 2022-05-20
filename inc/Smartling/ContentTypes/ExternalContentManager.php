<?php

namespace Smartling\ContentTypes;

use Smartling\Helpers\PluginHelper;
use Smartling\Submissions\SubmissionEntity;

class ExternalContentManager
{
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
                $source[$handler->getPluginId()] = $handler->getContentFields($submission, $raw);
            }
        }

        return $source;
    }

    public function setExternalContent(array $content, SubmissionEntity $submission): void
    {
        foreach ($this->handlers as $handler) {
            if (array_key_exists($handler->getPluginId(), $content) && $this->pluginHelper->canHandleExternalContent($handler)) {
                $handler->setContentFields($content[$handler->getPluginId()], $submission);
            }
        }
    }
}
