<?php

namespace Smartling\ContentTypes\Elementor;

use Smartling\Models\Content;
use Smartling\Models\RelatedContentInfo;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

interface Element {
    public function fromArray(array $array): self;
    public function getId(): string;
    public function getRelated(): RelatedContentInfo;
    public function getTranslatableStrings(): array;
    public function getType(): string;
    public function setRelations(
        Content $content,
        string $path,
        SubmissionEntity $submission,
        SubmissionManager $submissionManager,
    ): Element;
    public function setTargetContent(
        RelatedContentInfo $info,
        array $strings,
        SubmissionEntity $submission,
        SubmissionManager $submissionManager,
    ): self;
    public function toArray(): array;
}
