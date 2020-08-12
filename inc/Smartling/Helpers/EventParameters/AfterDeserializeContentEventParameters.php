<?php

namespace Smartling\Helpers\EventParameters;

use Smartling\DbAl\WordpressContentEntities\EntityAbstract;
use Smartling\Submissions\SubmissionEntity;

/**
 * Class AfterDeserializeContentEventParameters
 *
 * @package Smartling\Helpers\EventParameters
 */
class AfterDeserializeContentEventParameters
{

    /**
     * @var array
     */
    private $translatedFields;

    /**
     * @var SubmissionEntity
     */
    private $submission;

    /**
     * @var EntityAbstract
     */
    private $targetContent;

    /**
     * @var array
     */
    private $targetMetadata;
    private $context;


    /**
     * @param array $source
     * @param SubmissionEntity $submission
     * @param EntityAbstract $contentEntity
     * @param array $meta
     * @param string $context
     */
    public function __construct(
        array &$source,
        SubmissionEntity $submission,
        EntityAbstract $contentEntity,
        array $meta,
        $context
    )
    {
        $this->setTranslatedFields($source);
        $this->setSubmission($submission);
        $this->setTargetContent($contentEntity);
        $this->setTargetMetadata($meta);
        $this->context = $context;
    }

    /**
     * @return array
     */
    public function &getTranslatedFields()
    {
        return $this->translatedFields;
    }

    /**
     * @param array $translatedFields
     */
    private function setTranslatedFields(& $translatedFields)
    {
        $this->translatedFields = &$translatedFields;
    }

    /**
     * @return SubmissionEntity
     */
    public function getSubmission()
    {
        return $this->submission;
    }

    /**
     * @param SubmissionEntity $submission
     */
    private function setSubmission($submission)
    {
        $this->submission = $submission;
    }

    /**
     * @return EntityAbstract
     */
    public function getTargetContent()
    {
        return $this->targetContent;
    }

    /**
     * @param EntityAbstract $targetContent
     */
    private function setTargetContent($targetContent)
    {
        $this->targetContent = $targetContent;
    }

    /**
     * @return array
     */
    public function getTargetMetadata()
    {
        return $this->targetMetadata;
    }

    /**
     * @param array $targetMetadata
     */
    private function setTargetMetadata($targetMetadata)
    {
        $this->targetMetadata = $targetMetadata;
    }

    /**
     * @return string
     */
    public function getContext()
    {
        return $this->context;
    }
}