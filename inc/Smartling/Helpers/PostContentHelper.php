<?php

namespace Smartling\Helpers;

class PostContentHelper
{
    private $blockHelper;
    const PLACEHOLDER_FORMAT = '$RPH$%s$RPH$';

    public function __construct(GutenbergBlockHelper $blockHelper)
    {
        $this->blockHelper = $blockHelper;
    }

    /**
     * @param string $original
     * @param string $translated
     * @param array $lockedFields
     * @return string
     */
    public function applyTranslation($original, $translated, array $lockedFields)
    {
        $result = $this->blockHelper->normalizeCoreBlocks($translated);
        $originalBlocks = $this->blockHelper->addPostContentBlocks(['post_content' => $original]);
        $translatedBlocks = $this->blockHelper->addPostContentBlocks(['post_content' => $translated]);
        unset ($originalBlocks['post_content'], $translatedBlocks['post_content']);

        foreach ($translatedBlocks as $index => $block) {
            $result = $this->replaceFirstMatch($block, str_replace('%s', $index, self::PLACEHOLDER_FORMAT), $result);
        }

        foreach ($lockedFields as $lockedField) {
            $lockedField = preg_replace('~^entity/~', '', $lockedField);
            $placeholder = str_replace('%s', $lockedField, self::PLACEHOLDER_FORMAT);
            if (array_key_exists($lockedField, $originalBlocks) && array_key_exists($lockedField, $translatedBlocks)) {
                $result = str_replace($placeholder, $originalBlocks[$lockedField], $result);
            }
        }

        $placeholders = [];
        preg_match_all('~\$RPH\$([^$]+)\$RPH\$~', $result, $placeholders);
        foreach ($placeholders[0] as $index => $placeholder) {
            $result = str_replace($placeholder, $translatedBlocks[$placeholders[1][$index]], $result);
        }

        return $result;
    }

    private function replaceFirstMatch($search, $replace, $subject)
    {
        $pos = strpos($subject, $search);
        if ($pos === false) {
            return $subject;
        }

        return substr_replace($subject, $replace, $pos, strlen($search));
    }
}
