<?php

namespace Smartling\Helpers;

use Psr\Log\LoggerInterface;
use Smartling\DbAl\WordpressContentEntities\EntityAbstract;
use Smartling\DbAl\WordpressContentEntities\PostEntity;
use Smartling\DbAl\WordpressContentEntities\PostEntityStd;
use Smartling\DbAl\WordpressContentEntities\TaxonomyEntityAbstract;
use Smartling\DbAl\WordpressContentEntities\VirtualEntityAbstract;
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
        $existing_meta = $this->readTargetMetadata($submission);
        $this->getLogger()->debug(
            vsprintf('Removing ALL metadata for target content for submission %s', [$submission->getId()])
        );

        $wrapper = $this->getWrapper($submission->getContentType());
        $wpMetaFunction = null;


        $this->ensureTarget($submission);

        if ($wrapper instanceof PostEntityStd) {
            $wpMetaFunction = 'delete_post_meta';
        } elseif ($wrapper instanceof TaxonomyEntityAbstract) {
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
            } catch (\Exception $e) {
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
}