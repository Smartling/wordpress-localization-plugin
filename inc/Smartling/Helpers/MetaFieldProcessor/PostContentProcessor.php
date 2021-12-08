<?php

namespace Smartling\Helpers\MetaFieldProcessor;

use Smartling\Helpers\GutenbergBlockHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Models\GutenbergBlock;
use Smartling\Submissions\SubmissionEntity;

class PostContentProcessor implements MetaFieldProcessorInterface
{
    private GutenbergBlockHelper $blockHelper;
    private WordpressFunctionProxyHelper $wordpressProxy;

    public function __construct(GutenbergBlockHelper $blockHelper, WordpressFunctionProxyHelper $wordpressProxy)
    {
        $this->blockHelper = $blockHelper;
        $this->wordpressProxy = $wordpressProxy;
    }

    public function getFieldRegexp(): string
    {
        return 'entity/post_content';
    }

    public function processFieldPostTranslation(SubmissionEntity $submission, $fieldName, $value)
    {
        return $value;
    }

    public function processFieldPreTranslation(SubmissionEntity $submission, $fieldName, $value, array $collectedFields): string
    {
        if ($this->blockHelper->hasBlocks($value)) {
            $result = '';
            $blocks = $this->blockHelper->parseBlocks($value);
            foreach ($blocks as $block) {
                $result .=  $this->wordpressProxy->serialize_block($this->blockHelper->replacePreTranslateBlockContent($block)->toArray());
            }
            return $result;
        }
        return $value;
    }
}
