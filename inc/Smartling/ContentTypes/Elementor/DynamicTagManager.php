<?php

namespace Smartling\ContentTypes\Elementor;

interface DynamicTagManager
{
    public function tag_text_to_tag_data(string $tagText): ?array;

    public function tag_data_to_tag_text(string $tagId, string $tagName, array $settings): string;
}
