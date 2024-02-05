<?php

namespace Smartling\Extensions;

use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Services\HandlerManager;
use Smartling\Submissions\SubmissionEntity;

class Redirection extends PluggableAbstract implements StringHandler {
    use LoggerSafeTrait;
    public function getMaxVersion(): string
    {
        return '5';
    }

    public function getMinVersion(): string
    {
        return '5';
    }

    public function getPluginId(): string
    {
        return 'redirection';
    }

    public function getPluginPaths(): array
    {
        return ['redirection/redirection.php'];
    }

    public function handle(string $string, ?HandlerManager $handlerManager, ?SubmissionEntity $submission): string
    {
        if ($submission === null || $this->getPluginSupportLevel() !== Pluggable::SUPPORTED || parse_url($string) === false) {
            return $string;
        }

        $currentBlogHost = parse_url($this->wpProxy->get_home_url(), PHP_URL_HOST);
        if (!is_string($currentBlogHost)) {
            return $string;
        }

        $source = parse_url($string);
        if (!is_array($source) || (array_key_exists('host', $source) && $currentBlogHost !== $source['host'])) {
            return $string;
        }

        if (class_exists('\Red_Item') && array_key_exists('path', $source)) {
            try {
                if (count(\Red_Item::get_for_url($source['path'])) > 0) {
                    $target = parse_url($this->wpProxy->get_home_url($submission->getTargetBlogId()));
                    $result = $target['scheme'] . '://' . $target['host'] . $source['path'];
                    if (array_key_exists('query', $source)) {
                        $result .= '?' . $source['query'];
                    }
                    if (array_key_exists('fragment', $source)) {
                        $result .= '#' . $source['fragment'];
                    }
                    return $result;
                }
            } catch (\Throwable $e) {
                $this->getLogger()->notice("Caught exception while getting Redirection for url=$string: {$e->getMessage()}");
            }
        }

        return $string;
    }
}
