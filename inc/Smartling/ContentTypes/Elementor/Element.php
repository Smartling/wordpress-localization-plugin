<?php

namespace Smartling\ContentTypes\Elementor;

use Smartling\ContentTypes\ExternalContentElementor;
use Smartling\Models\Content;
use Smartling\Models\RelatedContentInfo;
use Smartling\Submissions\SubmissionEntity;

interface Element {
    public function fromArray(array $array): self;
    public function getId(): string;
    public function getRelated(): RelatedContentInfo;
    public function getTranslatableStrings(): array;
    public function getType(): string;
    public function setRelations(
        Content $content,
        ExternalContentElementor $externalContentElementor,
        string $path,
        SubmissionEntity $submission,
    ): self;
    public function setTargetContent(
        ExternalContentElementor $externalContentElementor,
        RelatedContentInfo $info,
        array $strings,
        SubmissionEntity $submission,
    ): self;
    public function toArray(): array;
}
