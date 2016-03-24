<?php

namespace Smartling\Helpers\EventParameters;

/**
 * Class SmartlingFileUriFilterParamater
 * @package Smartling\Helpers\EventParameters
 */
class SmartlingFileUriFilterParamater
{
    /**
     * @var int
     */
    private $sourceBlogId;

    /**
     * @var int
     */
    private $sourceContentId;

    /**
     * @var string
     */
    private $fileUri;

    /**
     * @var string
     */
    private $contentType;

    /**
     * @return int
     */
    public function getSourceBlogId()
    {
        return $this->sourceBlogId;
    }

    /**
     * @param int $sourceBlogId
     *
     * @return SmartlingFileUriFilterParamater
     */
    public function setSourceBlogId($sourceBlogId)
    {
        $this->sourceBlogId = $sourceBlogId;

        return $this;
    }

    /**
     * @return int
     */
    public function getSourceContentId()
    {
        return $this->sourceContentId;
    }

    /**
     * @param int $sourceContentId
     *
     * @return SmartlingFileUriFilterParamater
     */
    public function setSourceContentId($sourceContentId)
    {
        $this->sourceContentId = $sourceContentId;

        return $this;
    }

    /**
     * @return string
     */
    public function getFileUri()
    {
        return $this->fileUri;
    }

    /**
     * @param string $fileUri
     *
     * @return SmartlingFileUriFilterParamater
     */
    public function setFileUri($fileUri)
    {
        $this->fileUri = $fileUri;

        return $this;
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
     *
     * @return SmartlingFileUriFilterParamater
     */
    public function setContentType($contentType)
    {
        $this->contentType = $contentType;

        return $this;
    }
}