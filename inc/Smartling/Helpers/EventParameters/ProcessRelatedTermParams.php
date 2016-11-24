<?php

namespace Smartling\Helpers\EventParameters;

use Smartling\Submissions\SubmissionEntity;

class ProcessRelatedTermParams
{
    /**
     * @var SubmissionEntity
     */
    private $submission;

    /**
     * @var string
     */
    private $contentType;

    /**
     * @var array
     */
    private $accumulator;

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
    public function setSubmission($submission)
    {
        $this->submission = $submission;
    }

    /**
     * @return string
     */
    public function getContentType()
    {
        return $this->contentType;
    }

    /**
     * @param string $contentType
     */
    public function setContentType($contentType)
    {
        $this->contentType = $contentType;
    }

    /**
     * @return array
     */
    public function & getAccumulator()
    {
        return $this->accumulator;
    }

    /**
     * @param array $accumulator
     */
    public function setAccumulator(& $accumulator)
    {
        $this->accumulator = & $accumulator;
    }


    /**
     * ProcessRelatedTermParams constructor.
     *
     * @param SubmissionEntity $submission
     * @param string           $contentType
     * @param array            $accumulator
     */
    public function __construct(SubmissionEntity $submission, $contentType, array & $accumulator)
    {
        $this->setSubmission($submission);
        $this->setContentType($contentType);
        $this->setAccumulator($accumulator);
    }
}