<?php

namespace Smartling\ContentTypes;

use JetBrains\PhpStorm\ExpectedValues;
use Smartling\Extensions\Pluggable;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\Submission;

interface ContentTypePluggableInterface extends Pluggable {

    #[ExpectedValues(valuesFromClass: Pluggable::class)]
    public function getSupportLevel(string $contentType, ?int $contentId = null): string;

    public function getContentFields(SubmissionEntity $submission, bool $raw): array;

    public function getExternalContentTypes(): array;

    public function getRelatedContent(string $contentType, int $contentId): array;

    public function setContentFields(array $original, array $translation, Submission $submission): ?array;
}
