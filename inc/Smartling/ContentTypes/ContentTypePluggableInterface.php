<?php

namespace Smartling\ContentTypes;

use Smartling\Helpers\PluginHelper;
use Smartling\Submissions\SubmissionEntity;

interface ContentTypePluggableInterface
{
    public function canHandle(PluginHelper $pluginHelper): bool;

    public function getContentFields(SubmissionEntity $submission, bool $raw): array;

    public function getMaxVersion(): string;

    public function getMinVersion(): string;

    public function getPluginId(): string;

    public function getPluginPath(): string;

    public function getRelatedContent(string $contentType, int $id, array $targetBlogIds): array;

    public function setContentFields(array $original, array $translation, SubmissionEntity $submission): ?array;
}
