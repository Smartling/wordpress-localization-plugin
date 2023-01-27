<?php

namespace Smartling\Helpers\EventParameters;

use Smartling\DbAl\WordpressContentEntities\Entity;
use Smartling\Submissions\SubmissionEntity;

class AfterDeserializeContentEventParameters
{
    private array $translatedFields;

    private SubmissionEntity $submission;

    private Entity $targetContent;

    private array $targetMetadata;

    public function __construct(
        array &$source,
        SubmissionEntity $submission,
        Entity $contentEntity,
        array $meta
    )
    {
        $this->translatedFields = &$source;
        $this->submission = $submission;
        $this->targetContent = $contentEntity;
        $this->targetMetadata = $meta;
    }

    public function &getTranslatedFields(): array
    {
        return $this->translatedFields;
    }

    public function getSubmission(): SubmissionEntity
    {
        return $this->submission;
    }

    public function getTargetContent(): Entity
    {
        return $this->targetContent;
    }

    public function getTargetMetadata(): array
    {
        return $this->targetMetadata;
    }
}
