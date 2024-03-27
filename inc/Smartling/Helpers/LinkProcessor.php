<?php

namespace Smartling\Helpers;

use Smartling\Extensions\StringHandler;
use Smartling\Services\HandlerManager;
use Smartling\Submissions\SubmissionEntity;

class LinkProcessor implements HandlerManager {
    use LoggerSafeTrait;
    private array $handlers = [];

    public function __construct(private SiteHelper $siteHelper)
    {
    }

    /** @noinspection PhpUnused, used in DI */
    public function addHandler(StringHandler $handler, int $priority = 10): void
    {
        if (!array_key_exists($priority, $this->handlers)) {
            $this->handlers[$priority] = [];
        }
        $this->handlers[$priority][] = $handler;
    }

    /**
     * @return StringHandler[]
     */
    public function getHandlerList(): array
    {
        ksort($this->handlers);

        return array_merge(...$this->handlers);
    }

    public function processUrl(string $url, SubmissionEntity $submission): string
    {
        return $this->siteHelper->withBlog($submission->getSourceBlogId(), function () use ($submission, $url) {
            foreach ($this->getHandlerList() as $handler) {
                try {
                    $result = $handler->handle($url, $this, $submission);
                    if ($result !== $url) {
                        $this->getLogger()->info('HandlerClass=' . get_class($handler) . " changed sourceUrl=$url to targetUrl=$result");
                        $url = $result;
                    }
                } catch (\Throwable $e) {
                    $this->getLogger()->notice('HandlerClass="' . get_class($handler) . '" got exception while processing url: ' . $e->getMessage());
                }
            }

            return $url;
        });
    }
}
