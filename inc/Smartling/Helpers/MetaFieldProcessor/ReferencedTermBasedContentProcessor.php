<?php

namespace Smartling\Helpers\MetaFieldProcessor;

use Smartling\Exception\SmartlingDbException;
use Smartling\Exception\SmartlingWpDataIntegrityException;

/**
 * Class ReferencedTermBasedContentProcessor
 * @package Smartling\Helpers\MetaFieldProcessor
 */
class ReferencedTermBasedContentProcessor extends ReferencedStdBasedContentProcessorAbstract
{

    /**
     * @param int $blogId
     * @param int $contentId
     *
     * @return mixed
     * @throws SmartlingWpDataIntegrityException
     */
    protected function detectRealContentType($blogId, $contentId)
    {
        try {
            $this->getContentHelper()->ensureBlog($blogId);
            $term = get_term($contentId, '', \ARRAY_A);
            $this->getContentHelper()->ensureRestoredBlogId();

            if (is_array($term)) {
                return $term['taxonomy'];
            } else {
                $message = vsprintf('The term-based content with id=\'%s\' not found in blog=\'%s\'', [$contentId,
                                                                                                       $blogId]);
                throw new SmartlingDbException($message);
            }
        } catch (\Exception $e) {
            $message = vsprintf('Error happened while detecting the real content type for content id=\'%s\' blog = \'%s\'', [$contentId,
                                                                                                                             $blogId]);
            throw new SmartlingWpDataIntegrityException($message, 0, $e);
        }
    }


}