<?php

namespace Smartling\ContentTypes;

use Smartling\Submissions\SubmissionEntity;

interface ContentTypePluggableInterface
{
    public function canHandle(string $contentType, int $contentId): bool;

    public function getContentFields(SubmissionEntity $submission, bool $raw): array;

    public function getMaxVersion(): string;

    public function getMinVersion(): string;

    public function getPluginId(): string;

    public function getPluginPath(): string;

    public function getRelatedContent(string $contentType, int $contentId): array;

    public function setContentFields(array $original, array $translation, SubmissionEntity $submission): ?array;
}
