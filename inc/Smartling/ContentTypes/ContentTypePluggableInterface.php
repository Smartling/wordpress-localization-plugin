<?php

namespace Smartling\ContentTypes;

use Smartling\Submissions\SubmissionEntity;

interface ContentTypePluggableInterface
{
    public function canHandle(string $contentType, ?int $contentId = null): bool;

    public function getContentFields(SubmissionEntity $submission, bool $raw): array;

    public function getExternalContentTypes(): array;

    public function getMaxVersion(): string;

    public function getMinVersion(): string;

    public function getPluginId(): string;

    public function getPluginPaths(): array;

    public function getRelatedContent(string $contentType, int $contentId): array;

    public function setContentFields(array $original, array $translation, SubmissionEntity $submission): ?array;
}
