<?php

namespace Smartling\Helpers\MetaFieldProcessor;

use Smartling\Exception\SmartlingDbException;
use Smartling\Exception\SmartlingWpDataIntegrityException;

class ReferencedPostBasedContentProcessor extends ReferencedStdBasedContentProcessorAbstract
{
    /**
     * @throws SmartlingWpDataIntegrityException
     */
    protected function detectRealContentType(int $blogId, int $contentId): string
    {
        try {
            $this->getContentHelper()->ensureBlog($blogId);
            $post = get_post($contentId);
            $this->getContentHelper()->ensureRestoredBlogId();

            if ($post instanceof \WP_Post) {
                return $post->post_type;
            }

            throw new SmartlingDbException("The post-based content contentId=\"$contentId\" not found in blogId=\"$blogId\"");
        } catch (\Exception $e) {
            throw new SmartlingWpDataIntegrityException("Error happened while detecting the real content type for contentId=\"$contentId\", blogId=\"$blogId\"", 0, $e);
        }
    }
}
