<?php

namespace Smartling\Helpers\EventParameters;

use Smartling\Submissions\SubmissionEntity;

/**
 * Class TranslationStringFilterParameters
 * @package Smartling\Helpers\EventParameters
 */
class TranslationStringFilterParameters
{
    /**
     * @var array
     */
    private $filterSettings;

    /**
     * @var SubmissionEntity
     */
    private $submission;

    /**
     * @var \DOMDocument
     */
    private $dom;

    /**
     * @var \DOMNode
     */
    private $node;

    /**
     * @return array
     */
    public function getFilterSettings()
    {
        return $this->filterSettings;
    }

    /**
     * @param array $filterSettings
     */
    public function setFilterSettings($filterSettings)
    {
        $this->filterSettings = $filterSettings;
    }

    /**
     * @return \DOMDocument
     */
    public function getDom()
    {
        return $this->dom;
    }

    /**
     * @param \DOMDocument $dom
     */
    public function setDom($dom)
    {
        $this->dom = $dom;
    }

    /**
     * @return \DOMNode
     */
    public function getNode()
    {
        return $this->node;
    }

    /**
     * @param \DOMNode $node
     */
    public function setNode($node)
    {
        $this->node = $node;
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
    public function setSubmission($submission)
    {
        $this->submission = $submission;
    }
}