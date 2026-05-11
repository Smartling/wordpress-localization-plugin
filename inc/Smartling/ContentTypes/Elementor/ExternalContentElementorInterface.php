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
}
