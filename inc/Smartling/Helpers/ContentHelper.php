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

    public function ensureBlog($blogId)
    {
        $this->setNeedBlogSwitch($this->getSiteHelper()->getCurrentBlogId() !== $blogId);

        if ($this->isNeedBlogSwitch()) {
            $this->getSiteHelper()->switchBlogId($blogId);
        }
    }

    public function ensureSource(SubmissionEntity $submission)
    {
        $this->ensureBlog($submission->getSourceBlogId());
    }

    public function ensureTarget(SubmissionEntity $submission)
    {
        $this->ensureBlog($submission->getTargetBlogId());
    }

    public function ensureRestoredBlogId()
    {
        if ($this->isNeedBlogSwitch()) {
            $this->getSiteHelper()->restoreBlogId();
            $this->setNeedBlogSwitch(false);
        }
    }

    /**
     * @param $contentType
     *
     * @return EntityAbstract
     */
    private function getWrapper($contentType)
    {
        return clone $this->getIoFactory()->getHandler($contentType);
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
        $wrapper = $this->getWrapper($submission->getContentType());
        $this->ensureSource($submission);
        $sourceContent = $wrapper->get($submission->getSourceId());
        $this->ensureRestoredBlogId();

        return $sourceContent;
    }

    /**
     * @param SubmissionEntity $submission
     *
     * @return EntityAbstract
     */
    public function readTargetContent(SubmissionEntity $submission)
    {
        /**
         * @var EntityAbstract $wrapper
         */
        $wrapper = $this->getWrapper($submission->getContentType());
        $this->ensureTarget($submission);
        $targetContent = $wrapper->get($submission->getTargetId());
        $this->ensureRestoredBlogId();

        return $targetContent;
    }

    /**
     * @param SubmissionEntity $submission
     *
     * @return array
     */
    public function readSourceMetadata(SubmissionEntity $submission)
    {
        $metadata = [];
        /**
         * @var EntityAbstract $wrapper
         */
        $wrapper = $this->getWrapper($submission->getContentType());
        $this->ensureSource($submission);
        $sourceContent = $wrapper->get($submission->getSourceId());
        if ($sourceContent instanceof EntityAbstract) {
            $metadata = $sourceContent->getMetadata();
        }
        $this->ensureRestoredBlogId();

        return $metadata;
    }

    /**
     * @param SubmissionEntity $submission
     *
     * @return array
     */
    public function readTargetMetadata(SubmissionEntity $submission)
    {
        $metadata = [];
        /**
         * @var EntityAbstract $wrapper
         */
        $wrapper = $this->getWrapper($submission->getContentType());
        $this->ensureTarget($submission);
        $targetContent = $wrapper->get($submission->getTargetId());
        if ($targetContent instanceof EntityAbstract) {
            $metadata = $targetContent->getMetadata();
        }
        $this->ensureRestoredBlogId();

        return $metadata;
    }

    /**
     * @param SubmissionEntity $submission
     * @param EntityAbstract   $entity
     *
     * @return EntityAbstract
     */
    public function writeTargetContent(SubmissionEntity $submission, EntityAbstract $entity)
    {
        /**
         * @var EntityAbstract $wrapper
         */
        $wrapper = $this->getWrapper($submission->getContentType());

        $this->ensureTarget($submission);

        $result = $wrapper->set($entity);

        if (is_int($result) && 0 < $result) {

            $result = $wrapper->get($result);
        }

        $this->ensureRestoredBlogId();

        return $result;
    }

    /**
     * @param SubmissionEntity $submission
     * @param array            $metadata
     */
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

    public function removeTargetMetadata(SubmissionEntity $submission)
    {
        $this->ensureTarget($submission);
        $this->getLogger()
            ->debug(vsprintf('Removing ALL metadata for target content for submission %s', [$submission->getId()]));
        $result = delete_metadata($submission->getContentType(), $submission->getTargetId());
        $msg = 'Removing metadata for %s id=%s (submission = %s ) finished ' .
               (true === $result ? 'successfully' : 'with error');
        $this->getLogger()->debug(vsprintf($msg, [$submission->getContentType(), $submission->getTargetId(),
                                                  $submission->getId()]));
        $this->ensureRestoredBlogId();
    }
}