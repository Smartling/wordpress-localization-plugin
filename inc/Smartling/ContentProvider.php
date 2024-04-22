<?php

namespace Smartling;

use Smartling\Base\SmartlingCore;
use Smartling\DbAl\WordpressContentEntities\EntityWithMetadata;
use Smartling\DbAl\WordpressContentEntities\PostEntityStd;
use Smartling\DbAl\WordpressContentEntities\TaxonomyEntityStd;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\FileUriHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Models\Content;
use Smartling\Processors\ContentEntitiesIOFactory;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\Submissions\SubmissionSimple;

class ContentProvider {
    private bool $knownTypesAdded = false;
    public function __construct(
        private ContentHelper $contentHelper,
        private ContentEntitiesIOFactory $contentEntitiesIOFactory,
        private FileUriHelper $fileUriHelper,
        private SmartlingCore $core,
        private SubmissionManager $submissionManager,
        private WordpressFunctionProxyHelper $wordpressProxy,
    ) {
    }

    public function getAsset(Content $content): array
    {
        return $this->mapAssetDetails($this->wordpressProxy->get_post($content->getId()));
    }

    public function getAssets(
        ?string $assetType,
        ?string $searchTerm,
        int $limit = 30,
        string $sortBy = 'DESC',
        string $orderBy = 'ID',
    ): array {
        $this->addHandlersForRegisteredTypes();
        $arguments = [
            'numberposts' => $limit,
            'orderby' => $orderBy,
            'order' => $sortBy,
        ];

        if ($assetType() !== null) {
            $arguments['post_type'] = $assetType;
        }
        if ($searchTerm !== null) {
            $arguments['s'] = $searchTerm;
        }

        $posts = $this->wordpressProxy->get_posts($arguments);

        return array_map(function (\WP_Post $post) {
            return $this->mapAssetDetails($post);
        }, $posts);
    }

    public function getContent(Content $assetUid): string
    {
        $this->addHandlersForRegisteredTypes();
        return $this->core->getXMLFiltered($this->submissionManager->findOne([
            SubmissionEntity::FIELD_CONTENT_TYPE => $assetUid->getType(),
            SubmissionEntity::FIELD_SOURCE_BLOG_ID => $this->wordpressProxy->get_current_blog_id(),
            SubmissionEntity::FIELD_SOURCE_ID => $assetUid->getId(),
        ]));
    }

    public function getRawContent(Content $assetUid): array
    {
        $this->addHandlersForRegisteredTypes();
        $entity = $this->contentHelper->getWrapper($assetUid->getType())->get($assetUid->getId());
        $result = [
            'entity' => $entity->toArray(),
        ];
        if ($entity instanceof EntityWithMetadata) {
            $result['meta'] = $entity->getMetadata();
        }

        return $result;
    }

    public function getRelatedAssets(
        Content $assetUid,
        $limit = 10,
        $essential = false,
        $childDepth = 0,
        $relatedDepth = 0,
        $found = 0,
    ) {
        $arguments = [
            'numberposts' => $limit,
            'parent_id' => $assetUid->getId(),
        ];
        $children = null;
        while ($childDepth > 0) {
            $posts = $this->wordpressProxy->get_posts($arguments);
            $children = array_map(static function(\WP_Post $post) {
                return [
                    'assetUid' => $post->post_type . '-' . $post->ID,
                    'title' => $post->post_title,
                    'contentType' => null,
                    'isLocalizable' => true,
                    'hasChildren' => false,
                    'isFolder' => false,
                ];
            }, $posts);
            if ($found + count($children) >= $limit) {
                return $children;
            }
            foreach ($posts as $post) {
                $children = array_merge($children, $this->getRelatedAssets(new Content($post->ID, $post->post_type), $limit, $essential, $childDepth - 1, $relatedDepth, $found + count($children)));
            }
        }

        return $children ?? [];
    }

    public function mapAssetDetails(\WP_Post $post): array
    {
        return [
            'assetUid' => $post->post_type . '-' . $post->ID,
            'title' => $post->post_title,
            'isLocalizable' => true,
            'icon' => 'PAGE',
            'identity' => $post->ID,
            'externalLink' => $this->wordpressProxy->get_permalink($post),
            'fileUri' => $this->fileUriHelper->generateFileUri(new SubmissionSimple(
                $post->post_type,
                $this->wordpressProxy->get_current_blog_id(),
                $post->ID,
                $post->post_title,
            )),
            'sourceLocaleId' => '', // TODO discuss
            'contentType' => null, // TODO discuss
            'created' => $post->post_date_gmt,
            'updated' => $post->post_modified_gmt,
        ];
    }

    private function addHandlersForRegisteredTypes(): void
    {
        if ($this->knownTypesAdded) {
            return;
        }
        foreach ($this->wordpressProxy->get_taxonomies() as $taxonomy) {
            $this->contentEntitiesIOFactory->registerHandler($taxonomy, new TaxonomyEntityStd($taxonomy));
        }
        foreach ($this->wordpressProxy->get_post_types() as $postType) {
            $this->contentEntitiesIOFactory->registerHandler($postType, new PostEntityStd($postType));
        }
        $this->knownTypesAdded = true;
    }
}
