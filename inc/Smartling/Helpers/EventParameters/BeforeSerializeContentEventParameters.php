<?php

namespace Smartling\Helpers\EventParameters;

use Smartling\DbAl\WordpressContentEntities\EntityInterface;
use Smartling\Submissions\SubmissionEntity;

class BeforeSerializeContentEventParameters
{
    private array $preparedFields;

    private SubmissionEntity $submission;

    private EntityInterface $originalContent;

    private array $originalMetadata;

    public function __construct(
        array &$source,
        SubmissionEntity $submission,
        EntityInterface $contentEntity,
        array $meta
    )
    {
        $this->originalContent = $contentEntity;
        $this->originalMetadata = $meta;
        $this->preparedFields = &$source;
        $this->submission = $submission;
    }

    /**
     * @return array by reference for update
     */
    public function &getPreparedFields(): array
    {
        return $this->preparedFields;
    }

    public function getSubmission(): SubmissionEntity
    {
        return $this->submission;
    }

    public function getOriginalContent(): EntityInterface
    {
        return $this->originalContent;
    }

    public function getOriginalMetadata(): array
    {
        return $this->originalMetadata;
    }
}
