<?php

namespace Smartling\Helpers;

use Psr\Log\LoggerInterface;
use Smartling\Exception\BlogNotFoundException;
use Smartling\Exception\SmartlingDbException;
use Smartling\Exception\SmartlingDirectRunRuntimeException;
use Smartling\Exception\SmartlingInvalidFactoryArgumentException;
use Smartling\Processors\ContentEntitiesIOFactory;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

/**
 * Class CustomMenuContentTypeHelper
 *
 * @package Smartling\Helpers
 */
class CustomMenuContentTypeHelper
{

    /**
     * Wordpress meta_key name for nav_menu_item content type.
     */
    const META_KEY_MENU_ITEM_PARENT = '_menu_item_menu_item_parent';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ContentEntitiesIOFactory
     */
    private $contentIoFactory;

    /**
     * @var SiteHelper
     */
    private $siteHelper;

    /**
     * @var SubmissionManager
     */
    private $submissionManager;

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
     * @return ContentEntitiesIOFactory
     */
    public function getContentIoFactory()
    {
        return $this->contentIoFactory;
    }

    /**
     * @param ContentEntitiesIOFactory $contentIoFactory
     */
    public function setContentIoFactory($contentIoFactory)
    {
        $this->contentIoFactory = $contentIoFactory;
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
     * @return SubmissionManager
     */
    public function getSubmissionManager()
    {
        return $this->submissionManager;
    }

    /**
     * @param SubmissionManager $submissionManager
     */
    public function setSubmissionManager($submissionManager)
    {
        $this->submissionManager = $submissionManager;
    }

    /**
     * @param int   $menuId
     * @param int   $blogId
     * @param int[] $items
     *
     * @throws BlogNotFoundException
     */
    public function assignMenuItemsToMenu($menuId, $blogId, $items)
    {
        $needBlogChange = $blogId !== $this->getSiteHelper()->getCurrentBlogId();
        try {
            if ($needBlogChange) {
                $this->getSiteHelper()->switchBlogId($blogId);
            }

            foreach ($items as $item) {
                wp_set_object_terms($item, [(int)$menuId], WordpressContentTypeHelper::CONTENT_TYPE_NAV_MENU);
            }

            if ($needBlogChange) {
                $this->getSiteHelper()->restoreBlogId();
            }
        } catch (BlogNotFoundException $e) {
            throw $e;
        }
    }

    /**
     * @param int $menuId
     * @param int $blogId
     *
     * @return \Smartling\DbAl\WordpressContentEntities\MenuItemEntity[]
     * @throws BlogNotFoundException
     */
    public function getMenuItems($menuId, $blogId)
    {
        $options = [
            'order'                  => 'ASC',
            'orderby'                => 'menu_order',
            'post_type'              => WordpressContentTypeHelper::CONTENT_TYPE_NAV_MENU_ITEM,
            'post_status'            => 'publish',
            'output'                 => ARRAY_A,
            'output_key'             => 'menu_order',
            'nopaging'               => true,
            'update_post_term_cache' => false,
        ];

        $needBlogSwitch = $this->getSiteHelper()->getCurrentBlogId() !== $blogId;

        $ids = [];

        try {
            if ($needBlogSwitch) {
                $this->getSiteHelper()->switchBlogId($blogId);
            }

            $items = wp_get_nav_menu_items($menuId, $options);

            $mapper = $this->getContentIoFactory()->getMapper(WordpressContentTypeHelper::CONTENT_TYPE_NAV_MENU_ITEM);
            foreach ($items as $item) {
                $m = clone $mapper;
                $ids[] = $m->get((int)$item->ID);
            }

            if ($needBlogSwitch) {
                $this->getSiteHelper()->restoreBlogId();
            }
        } catch (BlogNotFoundException $e) {
            throw $e;
        }

        return $ids;
    }

    /**
     * @param SubmissionEntity $submission
     * @param string           $taxonomy
     *
     * @return array
     */
    public function getTerms($submission, $taxonomy)
    {
        $needBlogSwitch = $submission->getSourceBlogId() !== $this->getSiteHelper()->getCurrentBlogId();

        try {
            if ($needBlogSwitch) {
                $this->getSiteHelper()->switchBlogId($submission->getSourceBlogId());
            }

            $terms = wp_get_object_terms($submission->getSourceId(), $taxonomy);

            if ($needBlogSwitch) {
                $this->getSiteHelper()->restoreBlogId();
            }
        } catch (BlogNotFoundException $e) {
            $this->getLogger()->warning(vsprintf('Cannot get terms in missing blog.', []));
        }

        return null !== $terms && is_array($terms) ? $terms : [];
    }

    /**
     * @param int   $originalBlogId
     * @param int   $targetBlogId
     * @param int[] $items
     *
     * @throws \Exception
     */
    public function rebuildMenuHierarchy($originalBlogId, $targetBlogId, array $items)
    {
        $this->getLogger()->debug(vsprintf('Rebuilding menu hierarchy for menuItems=[%s]', [implode(',', $items)]));
        $contentType = WordpressContentTypeHelper::CONTENT_TYPE_NAV_MENU_ITEM;

        foreach ($items as $menuItemId) {
            try {

                $submission = $this->getSubmission($originalBlogId, $menuItemId, $targetBlogId, $contentType);

                $originalMenuItemParentId = (int)$this->getMenuItemParentByMenuItemId($originalBlogId, $menuItemId);

                if (0 === $originalMenuItemParentId) {
                    $this->setMenuItemParentByMenuItemId($submission->getTargetBlogId(), $submission->getTargetId(), 0);
                } else {
                    $parentSubmission = $this->getSubmission(
                        $originalBlogId,
                        $originalMenuItemParentId,
                        $targetBlogId,
                        $contentType);

                    if ($contentType === $parentSubmission->getContentType()) {
                        $newParentId = $parentSubmission->getTargetId();

                        $this->setMenuItemParentByMenuItemId(
                            $parentSubmission->getTargetBlogId(),
                            $submission->getTargetId(),
                            $newParentId
                        );
                    }
                }


            } catch (\Exception $e) {
                throw $e;
            }
        }
    }

    /**
     * @param int    $originalBlog
     * @param int    $originalId
     * @param int    $targetBlog
     * @param string $contentType
     *
     * @return SubmissionEntity
     *
     * @throws SmartlingDbException
     */
    private function getSubmission($originalBlog, $originalId, $targetBlog, $contentType)
    {
        $params = [
            'source_blog_id' => $originalBlog,
            'content_type'   => $contentType,
            'source_id'      => $originalId,
            'target_blog_id' => $targetBlog,
        ];

        $result = $this->getSubmissionManager()->find($params);

        if (0 < count($result)) {
            return ArrayHelper::first($result);
        } else {
            $message = vsprintf('No submissions found by request: %s', [var_export($params, true)]);
            throw new SmartlingDbException($message);
        }
    }


    /**
     * @param int    $blogId
     * @param int    $entityId
     * @param string $contentType
     * @param string $propertyName
     *
     * @return mixed
     *
     * @throws BlogNotFoundException
     * @throws \InvalidArgumentException
     * @throws SmartlingDirectRunRuntimeException
     * @throws SmartlingInvalidFactoryArgumentException
     */
    private function readMetaProperty($blogId, $entityId, $contentType, $propertyName)
    {
        $needBlogChange = $blogId !== $this->getSiteHelper()->getCurrentBlogId();
        if (true === $needBlogChange) {
            $this->getSiteHelper()->switchBlogId($blogId);
        }
        $mapper = $this->getContentIoFactory()->getMapper($contentType);
        $entity = $mapper->get($entityId);
        $metadata = $entity->getMetadata();
        if (true === $needBlogChange) {
            $this->getSiteHelper()->restoreBlogId();
        }
        unset ($entity, $mapper);
        if (array_key_exists($propertyName, $metadata)) {
            $value = $metadata[$propertyName];
            if (is_array($value)) {
                $value = ArrayHelper::first($value);
            }

            return $value;
        } else {
            $message = vsprintf(
                'meta_key=%s not found for content_type=%s id=%s in blog=%s',
                [
                    $propertyName,
                    $contentType,
                    $entityId,
                    $blogId,
                ]
            );
            throw new \InvalidArgumentException($message);
        }
    }

    /**
     * @param int    $blogId
     * @param int    $entityId
     * @param string $contentType
     * @param string $propertyName
     * @param mixed  $propertyValue
     *
     * @throws BlogNotFoundException
     * @throws SmartlingInvalidFactoryArgumentException
     * @throws SmartlingDirectRunRuntimeException
     */
    private function writeMetaProperty($blogId, $entityId, $contentType, $propertyName, $propertyValue)
    {
        $needBlogChange = $blogId !== $this->getSiteHelper()->getCurrentBlogId();
        if (true === $needBlogChange) {
            $this->getSiteHelper()->switchBlogId($blogId);
        }
        $mapper = $this->getContentIoFactory()->getMapper($contentType);
        $entity = $mapper->get($entityId);
        $entity->setMetaTag($propertyName, $propertyValue);
        if (true === $needBlogChange) {
            $this->getSiteHelper()->restoreBlogId();
        }
    }

    /**
     * @param $blogId
     * @param $menuItemId
     *
     * @return int
     *
     * @throws BlogNotFoundException
     * @throws SmartlingInvalidFactoryArgumentException
     * @throws \InvalidArgumentException
     * @throws SmartlingDirectRunRuntimeException
     */
    private function getMenuItemParentByMenuItemId($blogId, $menuItemId)
    {
        return $this->readMetaProperty(
            $blogId,
            $menuItemId,
            WordpressContentTypeHelper::CONTENT_TYPE_NAV_MENU_ITEM,
            self::META_KEY_MENU_ITEM_PARENT
        );
    }

    /**
     * @param int   $blogId
     * @param int   $menuItemId
     * @param mixed $value
     *
     * @throws BlogNotFoundException
     * @throws \InvalidArgumentException
     * @throws SmartlingDirectRunRuntimeException
     * @throws SmartlingInvalidFactoryArgumentException
     */
    private function setMenuItemParentByMenuItemId($blogId, $menuItemId, $value)
    {
        $this->writeMetaProperty(
            $blogId,
            $menuItemId,
            WordpressContentTypeHelper::CONTENT_TYPE_NAV_MENU_ITEM,
            self::META_KEY_MENU_ITEM_PARENT,
            $value
        );
    }
}