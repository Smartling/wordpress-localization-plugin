<?php

namespace Smartling\ContentTypes;

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
                $this->getLogger()->debug("Determined support for {$handler->getPluginId()}, will try to get fields");
                $source[$handler->getPluginId()] = $handler->getContentFields($submission, $raw);
            }
        }

        return $source;
    }

    public function setExternalContent(array $content, SubmissionEntity $submission): void
    {
        foreach ($this->handlers as $handler) {
            if (array_key_exists($handler->getPluginId(), $content) && $this->pluginHelper->canHandleExternalContent($handler)) {
                $this->getLogger()->debug("Determined support for {$handler->getPluginId()}, will try to set fields");
                $handler->setContentFields($content[$handler->getPluginId()], $submission);
            }
        }
    }
}
