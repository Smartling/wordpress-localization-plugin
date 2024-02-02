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
        if ($this->getPluginSupportLevel() !== Pluggable::SUPPORTED || parse_url($string) === false) {
            return $string;
        }

        $currentBlogHost = parse_url($this->wpProxy->get_home_url(), PHP_URL_HOST);
        if (!is_string($currentBlogHost)) {
            return $string;
        }

        $parsed = parse_url($string);
        if (!is_array($parsed) || ($currentBlogHost && $currentBlogHost !== $parsed['host'])) {
            return $string;
        }

        if (class_exists('\Red_Item') && array_key_exists('path', $parsed)) {
            try {
                $redirects = \Red_Item::get_for_url($parsed['path']);
                foreach ((array)$redirects as $item) {
                    $action = $item->get_match($parsed['path']);

                    if ($action) {
                        return $action->get_target();
                    }
                }
            } catch (\Throwable $e) {
                $this->getLogger()->notice("Caught exception while getting Redirection for url=$string: {$e->getMessage()}");
            }
        }

        return $string;
    }
}
