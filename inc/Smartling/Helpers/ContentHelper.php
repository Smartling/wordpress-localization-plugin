<?php

namespace Smartling\Helpers;

use Exception;
use Psr\Log\LoggerInterface;
use Smartling\DbAl\WordpressContentEntities\EntityAbstract;
use Smartling\DbAl\WordpressContentEntities\PostEntity;
use Smartling\DbAl\WordpressContentEntities\PostEntityStd;
use Smartling\DbAl\WordpressContentEntities\TaxonomyEntityStd;
use Smartling\DbAl\WordpressContentEntities\VirtualEntityAbstract;
use Smartling\MonologWrapper\MonologWrapper;
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
     * @return RuntimeCacheHelper
     */
    private function getRuntimeCache()
    {
        return RuntimeCacheHelper::getInstance();
    }

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
     * ContentHelper constructor.
     */
    public function __construct()
    {
        $this->logger = MonologWrapper::getLogger(get_called_class());
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
        if (false === ($cached = $this->getRuntimeCache()->get($submission->getId(), 'sourceContent'))) {
            /**
             * @var EntityAbstract $wrapper
             */
            $wrapper = $this->getWrapper($submission->getContentType());
            $this->ensureSource($submission);
            $sourceContent = $wrapper->get($submission->getSourceId());
            $this->ensureRestoredBlogId();
            $clone = clone $sourceContent;
            $this->getRuntimeCache()->set($submission->getId(), $sourceContent, 'sourceContent');
        } else {
            $clone = clone $cached;
        }
        return $clone;
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
        if (false === ($cached = $this->getRuntimeCache()->get($submission->getId(), 'sourceMeta'))) {
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

            $clone = $metadata;
            $this->getRuntimeCache()->set($submission->getId(), $metadata, 'sourceMeta');
        } else {
            $clone = $cached;
        }
        return $clone;
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
        $existing_meta = $this->readTargetMetadata($submission);
        $this->getLogger()->debug(
            vsprintf('Removing ALL metadata for target content for submission %s', [$submission->getId()])
        );

        $wrapper = $this->getWrapper($submission->getContentType());
        $wpMetaFunction = null;


        $this->ensureTarget($submission);

        if ($wrapper instanceof PostEntityStd) {
            $wpMetaFunction = 'delete_post_meta';
        } elseif ($wrapper instanceof TaxonomyEntityStd) {
            $wpMetaFunction = 'delete_term_meta';
        } elseif ($wrapper instanceof VirtualEntityAbstract) {
            /* Virtual types cannot have metadata */
        } else {
            $msgTemplate = 'Unknown content-type. Expected %s to be successor of one of: %s';
            $this->getLogger()->warning(
                vsprintf($msgTemplate,
                         [get_class($wrapper),
                          implode(',', ['PostEntity', 'TaxonomyEntityAbstract', 'VirtualEntityAbstract']),
                         ]
                )
            );
        }

        $object_id = $submission->getTargetId();
        if (null !== $wpMetaFunction) {
            try {
                foreach ($existing_meta as $key => $value) {
                    $result = call_user_func_array($wpMetaFunction, [$object_id, $key]);
                    $msg_template = 'Removing metadata key=\'%s\' for \'%s\' id=\'%s\' (submission = \'%s\' ) finished ' .
                                    (true === $result ? 'successfully' : 'with error');
                    $msg = vsprintf($msg_template, [$key, $submission->getContentType(), $submission->getTargetId(),
                                                    $submission->getId()]);
                    $this->getLogger()->debug($msg);
                }
            } catch (Exception $e) {
                $msg = vsprintf('Error while deleting target metadata for submission id=\'%s\'. Message: %s',
                                [
                                    $submission->getId(),
                                    $e->getMessage(),
                                ]);
                $this->getLogger()->warning($msg);
            }
        }

        $this->ensureRestoredBlogId();
    }

    public function checkEntityExists($blogId, $contentType, $contentId)
    {
        $needSiteSwitch = (int)$blogId !== $this->getSiteHelper()->getCurrentBlogId();
        $result         = false;

        if ($needSiteSwitch) {
            $this->getSiteHelper()->switchBlogId((int)$blogId);
        }

        try {
            if (($this->getIoFactory()->getMapper($contentType)->get($contentId)) instanceof EntityAbstract) {
                $result = true;
            }
        } catch (Exception $e) {
        }

        if ($needSiteSwitch) {
            $this->getSiteHelper()->restoreBlogId();
        }
        return $result;
    }
}