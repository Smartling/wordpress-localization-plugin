<?php

namespace Smartling\ContentTypes;

use Smartling\Helpers\PluginHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Submissions\SubmissionEntity;

interface ContentTypePluggableInterface
{
    public function canHandle(PluginHelper $pluginHelper, int $contentId, WordpressFunctionProxyHelper $wpProxy): bool;

    public function getContentFields(SubmissionEntity $submission, bool $raw): array;

    public function getMaxVersion(): string;

    public function getMinVersion(): string;

    public function getPluginId(): string;

    public function getPluginPath(): string;

    public function getRelatedContent(string $contentType, int $id): array;

    public function setContentFields(array $original, array $translation, SubmissionEntity $submission): ?array;
}
