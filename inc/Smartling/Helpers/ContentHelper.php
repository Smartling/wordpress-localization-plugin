<?php

namespace Smartling\Helpers;

use Exception;
use Smartling\Base\ExportedAPI;
use Smartling\DbAl\WordpressContentEntities\Entity;
use Smartling\DbAl\WordpressContentEntities\EntityHandler;
use Smartling\DbAl\WordpressContentEntities\EntityWithMetadata;
use Smartling\DbAl\WordpressContentEntities\PostEntityStd;
use Smartling\DbAl\WordpressContentEntities\TaxonomyEntityStd;
use Smartling\DbAl\WordpressContentEntities\VirtualEntityAbstract;
use Smartling\Exception\EntityNotFoundException;
use Smartling\Processors\ContentEntitiesIOFactory;
use Smartling\Submissions\SubmissionEntity;

/**
 * A Small wrapper to read/write Registered entities and metadata by submission
 * Class ContentHelper
 * @package Smartling\Helpers
 */
class ContentHelper
{
    use LoggerSafeTrait;

    private bool $needBlogSwitch;

    private function getRuntimeCache(): RuntimeCacheHelper
    {
        return RuntimeCacheHelper::getInstance();
    }

    public function getIoFactory(): ContentEntitiesIOFactory
    {
        return $this->ioFactory;
    }

    public function getSiteHelper(): SiteHelper
    {
        return $this->siteHelper;
    }

    public function __construct(
        private ContentEntitiesIOFactory $ioFactory,
        private SiteHelper $siteHelper,
        private WordpressFunctionProxyHelper $wordpressFunctionProxyHelper,
    ) {
    }

    public function setNeedBlogSwitch(bool $needBlogSwitch): void
    {
        $this->needBlogSwitch = $needBlogSwitch;
    }

    public function ensureBlog($blogId): void
    {
        $this->setNeedBlogSwitch($this->getSiteHelper()->getCurrentBlogId() !== $blogId);

        if ($this->needBlogSwitch) {
            $this->getSiteHelper()->switchBlogId($blogId);
        }
    }

    public function ensureSourceBlogId(SubmissionEntity $submission): void
    {
        $this->ensureBlog($submission->getSourceBlogId());
    }

    public function ensureTargetBlogId(SubmissionEntity $submission): void
    {
        $this->ensureBlog($submission->getTargetBlogId());
    }

    public function ensureRestoredBlogId(): void
    {
        if ($this->needBlogSwitch) {
            $this->getSiteHelper()->restoreBlogId();
            $this->setNeedBlogSwitch(false);
        }
    }

    public function getWrapper(string $contentType): EntityHandler
    {
        $return = clone $this->getIoFactory()->getHandler($contentType);
        if (!$return instanceof EntityHandler) {
            throw new \RuntimeException("Handler for $contentType expected to be " . EntityHandler::class . ", factory returned " . get_class($return));
        }

        return $return;
    }

    /**
     * @throws EntityNotFoundException
     */
    public function readSourceContent(SubmissionEntity $submission): Entity
    {
        if (false === ($cached = $this->getRuntimeCache()->get($this->getCacheKey($submission), 'sourceContent'))) {
            $wrapper = $this->getWrapper($submission->getContentType());
            $this->ensureSourceBlogId($submission);
            $sourceContent = $wrapper->get($submission->getSourceId());
            $this->ensureRestoredBlogId();
            $clone = clone $sourceContent;
            $this->getRuntimeCache()->set($this->getCacheKey($submission), $sourceContent, 'sourceContent');
        } else {
            $clone = clone $cached;
        }
        return $clone;
    }

    /**
     * @throws EntityNotFoundException
     */
    public function readTargetContent(SubmissionEntity $submission): Entity
    {
        $wrapper = $this->getWrapper($submission->getContentType());
        $this->ensureTargetBlogId($submission);
        $targetContent = $wrapper->get($submission->getTargetId());
        $this->ensureRestoredBlogId();

        return $targetContent;
    }

    public function readSourceMetadata(SubmissionEntity $submission): array
    {
        if (false === ($cached = $this->getRuntimeCache()->get($this->getCacheKey($submission), 'sourceMeta'))) {
            $metadata = [];
            $wrapper = $this->getWrapper($submission->getContentType());
            $this->ensureSourceBlogId($submission);
            $sourceContent = $wrapper->get($submission->getSourceId());
            if ($sourceContent instanceof EntityWithMetadata) {
                $metadata = $sourceContent->getMetadata();
            }
            $this->ensureRestoredBlogId();

            $clone = $metadata;
            $this->getRuntimeCache()->set($this->getCacheKey($submission), $metadata, 'sourceMeta');
        } else {
            $clone = $cached;
        }
        return $clone;
    }

    public function readTargetMetadata(SubmissionEntity $submission): array
    {
        $metadata = [];
        $wrapper = $this->getWrapper($submission->getContentType());
        $this->ensureTargetBlogId($submission);
        $targetContent = $wrapper->get($submission->getTargetId());
        if ($targetContent instanceof EntityWithMetadata) {
            $metadata = $targetContent->getMetadata();
        }
        $this->ensureRestoredBlogId();

        return $metadata;
    }

    public function writeTargetContent(SubmissionEntity $submission, Entity $entity): Entity
    {
        $this->getLogger()->info(sprintf('Writing target content for submissionId=%d, contentType=%s, sourceBlogId=%d, sourceId=%d, targetBlogId=%d, targetId=%d', $submission->getId(), $submission->getContentType(), $submission->getSourceBlogId(), $submission->getSourceId(), $submission->getTargetBlogId(), $submission->getTargetId()));
        $wrapper = $this->getWrapper($submission->getContentType());

        $this->ensureTargetBlogId($submission);

        $result = $wrapper->set($entity);

        if (0 < $result) {
            try {
                $result = $wrapper->get($result);
            } catch (EntityNotFoundException $e) {
                $this->getLogger()->error(sprintf(
                    'Unable to get content after setting, contentId=%d, targetBlogId=%d, currentBlogId=%d',
                    $result,
                    $submission->getTargetBlogId(),
                    $this->wordpressFunctionProxyHelper->get_current_blog_id(),
                ));
                throw $e;
            }
        }

        $this->ensureRestoredBlogId();
        do_action(ExportedAPI::ACTION_AFTER_TARGET_CONTENT_WRITTEN, $submission);

        return $result;
    }

    public function writeTargetMetadata(SubmissionEntity $submission, array $metadata): void
    {
        $wrapper = $this->getWrapper($submission->getContentType());
        $this->ensureTargetBlogId($submission);
        $targetEntity = $wrapper->get($submission->getTargetId());
        if ($targetEntity instanceof EntityWithMetadata) {
            foreach ($metadata as $key => $value) {
                $targetEntity->setMetaTag($key, $value);
            }
        }
        $this->ensureRestoredBlogId();

        do_action(ExportedAPI::ACTION_AFTER_TARGET_METADATA_WRITTEN, $submission);
    }

    public function removeTargetMetadata(SubmissionEntity $submission): void
    {
        $existing_meta = $this->readTargetMetadata($submission);
        $this->getLogger()->debug(
            vsprintf('Removing ALL metadata for target content for submission %s', [$submission->getId()])
        );

        $wrapper = $this->getWrapper($submission->getContentType());
        $wpMetaFunction = null;

        $this->ensureTargetBlogId($submission);

        if ($wrapper instanceof PostEntityStd) {
            $wpMetaFunction = 'delete_post_meta';
        } elseif ($wrapper instanceof TaxonomyEntityStd) {
            $wpMetaFunction = 'delete_term_meta';
        } elseif (!$wrapper instanceof VirtualEntityAbstract) {
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
                    $result = $wpMetaFunction($object_id, $key);
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

    public function checkEntityExists(int $blogId, string $contentType, int $contentId): bool
    {
        $needSiteSwitch = $blogId !== $this->getSiteHelper()->getCurrentBlogId();
        $result = false;

        if ($needSiteSwitch) {
            $this->getSiteHelper()->switchBlogId($blogId);
        }

        try {
            if (($this->getIoFactory()->getMapper($contentType)->get($contentId)) instanceof Entity) {
                $result = true;
            }
        } catch (Exception) {
        }

        if ($needSiteSwitch) {
            $this->getSiteHelper()->restoreBlogId();
        }
        return $result;
    }

    private function getCacheKey(SubmissionEntity $entity): string
    {
        return implode('-', [$entity->getContentType(), $entity->getSourceBlogId(), $entity->getSourceId()]);
    }
}
