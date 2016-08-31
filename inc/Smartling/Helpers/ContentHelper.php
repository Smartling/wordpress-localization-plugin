<?php

namespace Smartling\Helpers;

use Psr\Log\LoggerInterface;
use Smartling\DbAl\WordpressContentEntities\EntityAbstract;
use Smartling\Processors\ContentEntitiesIOFactory;
use Smartling\Submissions\SubmissionEntity;

/**
 * A Small wrapper to read/write Registered entities and metadata by submission
 * Class ContentHelper
 * @package Smartling\Helpers
 */
class ContentHelper
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ContentEntitiesIOFactory
     */
    private $ioFactory;

    /**
     * @var SiteHelper
     */
    private $siteHelper;

    /**
     * @var bool
     */
    private $needBlogSwitch;

    /**
     * @return ContentEntitiesIOFactory
     */
    public function getIoFactory()
    {
        return $this->ioFactory;
    }

    /**
     * @param ContentEntitiesIOFactory $ioFactory
     */
    public function setIoFactory($ioFactory)
    {
        $this->ioFactory = $ioFactory;
    }

    /**
     * @return SiteHelper
     */
    public function getSiteHelper()
    {
        return $this->siteHelper;
    }

    /**
     * @param SiteHelper $siteHelper
     */
    public function setSiteHelper($siteHelper)
    {
        $this->siteHelper = $siteHelper;
    }

    /**
     * @return boolean
     */
    public function isNeedBlogSwitch()
    {
        return $this->needBlogSwitch;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    /**
     * ContentHelper constructor.
     *
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->setLogger($logger);
    }

    /**
     * @param boolean $needBlogSwitch
     */
    public function setNeedBlogSwitch($needBlogSwitch)
    {
        $this->needBlogSwitch = $needBlogSwitch;
    }

    private function ensureBlog($blogId)
    {
        $this->setNeedBlogSwitch($this->getSiteHelper()->getCurrentBlogId() !== $blogId);

        if ($this->isNeedBlogSwitch()) {
            $this->getSiteHelper()->switchBlogId($blogId);
        }
    }

    private function ensureSource(SubmissionEntity $submission)
    {
        $this->ensureBlog($submission->getSourceBlogId());
    }

    private function ensureTarget(SubmissionEntity $submission)
    {
        $this->ensureBlog($submission->getTargetBlogId());
    }

    private function ensureRestoredBlogId()
    {
        if ($this->isNeedBlogSwitch()) {
            $this->getSiteHelper()->restoreBlogId();
            $this->setNeedBlogSwitch(false);
        }
    }

    /**
     * @param SubmissionEntity $submission
     *
     * @return EntityAbstract
     */
    public function readSourceContent(SubmissionEntity $submission)
    {
        /**
         * @var EntityAbstract $wrapper
         */
        $wrapper = $this->getIoFactory()->getHandler($submission->getContentType());
        $this->ensureSource($submission);
        $sourceContent = $wrapper->get($submission->getSourceId());
        $this->ensureRestoredBlogId();

        return $sourceContent;
    }

    public function readTargetContent(SubmissionEntity $submission)
    {
        /**
         * @var EntityAbstract $wrapper
         */
        $wrapper = $this->getIoFactory()->getHandler($submission->getContentType());
        $this->ensureTarget($submission);
        $targetContent = $wrapper->get($submission->getTargetId());
        $this->ensureRestoredBlogId();

        return $targetContent;
    }

    public function readSourceMetadata(SubmissionEntity $submission)
    {
        $metadata = [];
        /**
         * @var EntityAbstract $wrapper
         */
        $wrapper = $this->getIoFactory()->getHandler($submission->getContentType());
        $this->ensureSource($submission);
        $sourceContent = $wrapper->get($submission->getSourceId());
        if ($sourceContent instanceof EntityAbstract) {
            $metadata = $sourceContent->getMetadata();
        }
        $this->ensureRestoredBlogId();

        return $metadata;
    }

    public function readTargetMetadata(SubmissionEntity $submission)
    {
        $metadata = [];
        /**
         * @var EntityAbstract $wrapper
         */
        $wrapper = $this->getIoFactory()->getHandler($submission->getContentType());
        $this->ensureTarget($submission);
        $targetContent = $wrapper->get($submission->getTargetId());
        if ($targetContent instanceof EntityAbstract) {
            $metadata = $targetContent->getMetadata();
        }
        $this->ensureRestoredBlogId();

        return $metadata;
    }

    public function writeTargetContent(SubmissionEntity $submission, EntityAbstract $entity)
    {
        /**
         * @var EntityAbstract $wrapper
         */
        $wrapper = $this->getIoFactory()->getHandler($submission->getContentType());
        $this->ensureTarget($submission);
        $result = $wrapper->set($entity);
        $this->ensureRestoredBlogId();

        return $result;
    }

    public function writeTargetMetadata(SubmissionEntity $submission, array $metadata)
    {
        /**
         * @var EntityAbstract $wrapper
         */
        $wrapper = $this->getIoFactory()->getHandler($submission->getContentType());
        $this->ensureTarget($submission);
        $targetEntity = $wrapper->get($submission->getTargetId());
        if ($targetEntity instanceof EntityAbstract) {
            foreach ($metadata as $key => $value) {
                $targetEntity->setMetaTag($key, $value);
            }
        }
        $this->ensureRestoredBlogId();
    }
}