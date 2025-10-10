<?php

namespace Smartling\ContentTypes\Elementor;

class DynamicTagsManagerShim
{
    public function parse_tag_text(string $tagText, array $settings, callable $parseCallback): mixed
    {
        $tagData = $this->tag_text_to_tag_data($tagText);

        if (!$tagData) {
            if (!empty($settings['returnType']) && 'object' === $settings['returnType']) {
                return [];
            }

            return '';
        }

        return call_user_func_array($parseCallback, array_values($tagData));
    }

    public function tag_data_to_tag_text(string $tagId, string $tagName, array $settings): string
    {
        return sprintf(
            '[%1$s id="%2$s" name="%3$s" settings="%4$s"]',
            'elementor-tag',
            $tagId,
            $tagName,
            urlencode(json_encode($settings, JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT)),
        );
    }

    public function tag_text_to_tag_data(string $tagText): ?array
    {
        $parts = explode(' ', $tagText);
        $decoded = urldecode(substr(explode('=', $parts[3])[1], 1, -2));

        return [
            'id' => substr(explode('=', $parts[1])[1], 1, -1),
            'name' => substr(explode('=', $parts[2])[1], 1, -1),
            'settings' => json_decode($decoded, true, flags: JSON_THROW_ON_ERROR),
        ];
    }
}
