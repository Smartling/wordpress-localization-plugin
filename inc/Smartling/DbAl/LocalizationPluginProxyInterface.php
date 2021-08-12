<?php

namespace Smartling\DbAl;

use Psr\Log\LoggerInterface;
use Smartling\Helpers\SiteHelper;
use Smartling\Submissions\SubmissionEntity;

interface LocalizationPluginProxyInterface
{
    public function addHooks(): void;

    /**
     * Retrieves locale from site option
     */
    public function getBlogLocaleById(int $blogId): string;

    public function isActive(): bool;

    public function linkObjects(SubmissionEntity $submission): bool;

    public function unlinkObjects(SubmissionEntity $submission): bool;

    public function getBlogNameByLocale(string $locale): string;
}
