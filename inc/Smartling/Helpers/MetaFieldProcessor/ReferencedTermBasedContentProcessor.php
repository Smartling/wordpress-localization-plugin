<?php

namespace Smartling\Helpers\MetaFieldProcessor;

use Smartling\Exception\SmartlingDbException;
use Smartling\Exception\SmartlingWpDataIntegrityException;

class ReferencedTermBasedContentProcessor extends ReferencedStdBasedContentProcessorAbstract
{
    /**
     * @throws SmartlingWpDataIntegrityException
     */
    protected function detectRealContentType(int $blogId, int $contentId): string
    {
        try {
            $this->getContentHelper()->ensureBlog($blogId);
            $term = get_term($contentId, '', \ARRAY_A);
            $this->getContentHelper()->ensureRestoredBlogId();

            if (is_array($term)) {
                return $term['taxonomy'];
            }

            throw new SmartlingDbException("The term-based content contentId=\"$contentId\" not found in blogId=\"$blogId\"");
        } catch (\Exception $e) {
            throw new SmartlingWpDataIntegrityException("Error happened while detecting the real content type for contentId=\"$contentId\", blogId=\"$blogId\"", 0, $e);
        }
    }
}
