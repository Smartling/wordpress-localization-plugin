<?php

namespace Smartling\DbAl;

use Psr\Log\LoggerInterface;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Submissions\SubmissionEntity;

class DummyLocalizationPlugin implements LocalizationPluginProxyInterface
{
    public function addHooks(): void
    {
    }

    public function getBlogLocaleById(int $blogId): string
    {
        return '';
    }

    public function linkObjects(SubmissionEntity $submission): bool
    {
        return true;
    }

    public function unlinkObjects(SubmissionEntity $submission): bool
    {
        return true;
    }

    public function getBlogNameByLocale(string $locale): string
    {
        return '';
    }

    public function isActive(): bool
    {
        return true;
    }
}
