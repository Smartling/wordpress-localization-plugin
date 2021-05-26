<?php
/**
 * Plugin Name: Smartling Example attachment url rewrite
 * Plugin URI: http://smartling.com
 * Author: Smartling
 * Description: Rewrite attachment url to the target attachment url in Gutenberg block attributes
 * Version: 1.0
 */

use Smartling\Bootstrap;
use Smartling\Helpers\EventParameters\AfterDeserializeContentEventParameters;
use Smartling\Helpers\SiteHelper;
use Smartling\Models\GutenbergBlock;

add_action('plugins_loaded', static function () {
    add_action('smartling_after_deserialize_content', static function (AfterDeserializeContentEventParameters $params) {
        // This code will be executed every time when WP Connector applies translated content for every locale

        $gutenbergBlockHelper = Bootstrap::getContainer()->get('helper.gutenberg');
        $siteHelper = Bootstrap::getContainer()->get('site.helper');
        $submission = $params->getSubmission();

        array_walk_recursive($params->getTranslatedFields(), static function (&$value) use ($gutenbergBlockHelper, $siteHelper, $submission) {
            if (!$gutenbergBlockHelper->hasBlocks($value)) {
                return;
            }

            // Inside a block, replace properties in keys, using post ids from values
            $attributes = ['backgroundMediaUrl' => 'backgroundMediaId'];

            $blocks = [];
            foreach ($gutenbergBlockHelper->parseBlocks($value) as $block) {
                $blocks[] = replaceProperties($block, $attributes, $siteHelper, $submission->getTargetBlogId())->toArray();
            }
            $value = serialize_blocks($blocks);
        });

        return $params;
    });
});

/**
 * For example,
 * <!-- wp:sf/example {"backgroundMediaId":57,"backgroundMediaUrl":""} --> <!-- /wp:sf/example -->
 * would get replaced to
 * <!-- wp:sf/example {"backgroundMediaId":57,"backgroundMediaUrl":"https://fr.example.com/post-57"} --> <!-- /wp:sf/example -->
 */
function replaceProperties(GutenbergBlock $block, array $attributes, SiteHelper $siteHelper, int $targetBlogId): GutenbergBlock
{
    $blockAttributes = $block->getAttributes();
    $innerBlocks = [];

    foreach ($blockAttributes as $blockAttribute => $value) {
        if (array_key_exists($blockAttribute, $attributes)) {
            $searchKey = $attributes[$blockAttribute];
            if (array_key_exists($searchKey, $blockAttributes)) {
                $postId = $blockAttributes[$searchKey];
                $post = $siteHelper->withBlog($targetBlogId, static function () use ($postId) {
                    return get_post($postId);
                });
                if ($post instanceof WP_Post) {
                    $blockAttributes[$blockAttribute] = get_permalink($post);
                }
            }
        }
    }

    foreach ($block->getInnerBlocks() as $innerBlock) {
        $innerBlocks[] = replaceProperties($innerBlock, $attributes, $siteHelper, $targetBlogId);
    }

    return new GutenbergBlock($block->getBlockName(), $blockAttributes, $innerBlocks, $block->getInnerHtml(), $block->getInnerContent());
}
