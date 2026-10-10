<?php

namespace Smartling\ContentTypes\Elementor;

use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;

interface ExternalContentElementorInterface
{
    public function getWpProxy(): WordpressFunctionProxyHelper;

    public function getTargetId(
        int $sourceBlogId,
        int $sourceId,
        int $targetBlogId,
        string $contentType = ContentTypeHelper::POST_TYPE_ATTACHMENT,
    ): ?int;

    /**
     * @return int|null term_id of the term with the given term_taxonomy_id in the current blog
     */
    public function getTermId(int $termTaxonomyId): ?int;

    /**
     * @return int|null term_taxonomy_id of the term in the given blog
     */
    public function getTermTaxonomyId(int $blogId, int $termId): ?int;
}
