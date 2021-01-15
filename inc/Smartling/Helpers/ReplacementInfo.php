<?php

namespace Smartling\Helpers;

class ReplacementInfo
{
    private $replacement;
    private $sourceId;
    private $targetId;

    /**
     * @param string $replacement
     * @param int|string $sourceId
     * @param int|string $targetId
     */
    public function __construct($replacement, $sourceId, $targetId)
    {
        $this->replacement = $replacement;
        $this->sourceId = $sourceId;
        $this->targetId = $targetId;
    }

    /**
     * @return string
     */
    public function getReplacement()
    {
        return $this->replacement;
    }

    /**
     * @return int|string
     */
    public function getSourceId()
    {
        return $this->sourceId;
    }

    /**
     * @return int|string
     */
    public function getTargetId()
    {
        return $this->targetId;
    }
}
