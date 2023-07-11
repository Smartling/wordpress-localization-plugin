<?php

namespace Smartling\ContentTypes;

use JetBrains\PhpStorm\ExpectedValues;
use Smartling\Submissions\SubmissionEntity;

interface ContentTypePluggableInterface
{
    public const NOT_SUPPORTED = 'not_supported';
    public const SUPPORTED = 'supported';
    public const VERSION_NOT_SUPPORTED = 'version_not_supported';

    #[ExpectedValues(valuesFromClass: self::class)]
    public function getSupportLevel(string $contentType, ?int $contentId = null): string;

    public function getContentFields(SubmissionEntity $submission, bool $raw): array;

    public function getExternalContentTypes(): array;

    public function getMaxVersion(): string;

    public function getMinVersion(): string;

    public function getPluginId(): string;

    /**
     * @return array with possible paths to the plugin file relative to the plugins directory.
     * Most plugins have a single possible path, some have variations.
     */
    public function getPluginPaths(): array;

    public function getRelatedContent(string $contentType, int $contentId): array;

    public function setContentFields(array $original, array $translation, SubmissionEntity $submission): ?array;
}
