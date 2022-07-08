<?php

namespace Smartling\Tests\Smartling\ContentTypes;

use Smartling\ContentTypes\ExternalContentAioseo;
use PHPUnit\Framework\TestCase;
use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Helpers\PlaceholderHelper;
use Smartling\Helpers\SiteHelper;

class ExternalContentAioseoTest extends TestCase {
    public function testProcessContent()
    {
        if (!defined('OBJECT')) {
            define('OBJECT', 'OBJECT');
        }
        $x = $this->getExternalContentAioseo();

        $this->assertEquals([
            'title' => [PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_START . '#post_title #separator_sa #site_title' . PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_END],
            'description' => [PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_START . '#post_excerpt' . PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_END],
            'keywords' => [],
            'keyphrases' => ['focus/keyphrase' => 'Focus keyphrase'],
            'canonical_url' => null,
            'og_title' => ['FB Title'],
            'og_description' => ['FB Description'],
            'og_image_url' => null,
            'og_image_custom_url' => null,
            'og_image_custom_fields' => null,
            'og_video' => '',
            'og_custom_url' => null,
            'og_article_section' => ['Article seo section'],
            'og_article_tags' => [
                '0/label' => 'Seo tag',
                '0/value' => 'Seo tag'
            ],
            'twitter_image_url' => null,
            'twitter_image_custom_url' => null,
            'twitter_image_custom_fields' => null,
            'twitter_title' => null,
            'twitter_description' => null,
            'image_scan_date' => null,
            'video_scan_date' => null,
            'local_seo' => null,
            'created' => '2022-05-17 12:17:01',
            'updated' => '2022-05-17 12:17:40',
        ],
            $x->transformContentForUpload(
                json_decode('{"id":"41","post_id":"19232","title":"#post_title #separator_sa #site_title","description":"#post_excerpt","keywords":"[]","keyphrases":"{\"focus\":{\"keyphrase\":\"Focus keyphrase\",\"score\":50,\"analysis\":{\"keyphraseInTitle\":{\"title\":\"Focus keyphrase in SEO title\",\"description\":\"Focus keyphrase not found in SEO title.\",\"score\":3,\"maxScore\":9,\"error\":1},\"keyphraseInDescription\":[],\"keyphraseLength\":{\"title\":\"Focus keyphrase length\",\"description\":\"Good job!\",\"score\":9,\"maxScore\":9,\"error\":0,\"length\":2},\"keyphraseInURL\":{\"title\":\"Focus keyphrase in URL\",\"description\":\"Focus keyphrase not found in the URL.\",\"score\":1,\"maxScore\":5,\"error\":1},\"keyphraseInIntroduction\":{\"title\":\"Focus keyphrase in introduction\",\"description\":\"Your Focus keyphrase does not appear in the first paragraph. Make sure the topic is clear immediately.\",\"score\":3,\"maxScore\":9,\"error\":1},\"keyphraseInSubHeadings\":[],\"keyphraseInImageAlt\":[]}},\"additional\":[]}","page_analysis":"{\"analysis\":{\"basic\":{\"keyphraseInContent\":{\"title\":\"Focus keyphrase in content\",\"description\":\"Focus keyphrase not found in content.\",\"score\":3,\"maxScore\":9,\"error\":1},\"keyphraseInIntroduction\":{\"title\":\"Focus keyphrase in introduction\",\"description\":\"Your Focus keyphrase does not appear in the first paragraph. Make sure the topic is clear immediately.\",\"score\":3,\"maxScore\":9,\"error\":1},\"keyphraseInDescription\":[],\"keyphraseInURL\":{\"title\":\"Focus keyphrase in URL\",\"description\":\"Focus keyphrase not found in the URL.\",\"score\":1,\"maxScore\":5,\"error\":1},\"keyphraseLength\":{\"title\":\"Focus keyphrase length\",\"description\":\"Good job!\",\"score\":9,\"maxScore\":9,\"error\":0,\"length\":2},\"metadescriptionLength\":[],\"lengthContent\":{\"title\":\"Content length\",\"description\":\"This is far below the recommended minimum of words.\",\"score\":-20,\"maxScore\":9,\"error\":1},\"isInternalLink\":{\"title\":\"Internal links\",\"description\":\"We couldn\'t find any internal links in your content . Add internal links in your content . \",\"score\":3,\"maxScore\":9,\"error\":1},\"isExternalLink\":{\"title\":\"External links\",\"description\":\"No outbound links were found. Link out to external resources.\",\"score\":3,\"maxScore\":9,\"error\":1},\"errors\":6},\"title\":{\"keyphraseInTitle\":{\"title\":\"Focus keyphrase in SEO title\",\"description\":\"Focus keyphrase not found in SEO title.\",\"score\":3,\"maxScore\":9,\"error\":1},\"keyphraseInBeginningTitle\":{\"title\":\"Focus keyphrase at the beginning of SEO Title\",\"description\":\"Focus keyphrase doesn\'t appear at the beginning of SEO title.\",\"score\":3,\"maxScore\":9,\"error\":1},\"titleLength\":{\"title\":\"SEO Title length\",\"description\":\"The title is too short.\",\"score\":6,\"maxScore\":9,\"error\":1},\"errors\":3},\"readability\":{\"contentHasAssets\":{\"error\":1,\"title\":\"Images\\\/videos in content\",\"description\":\"You are not using rich media like images or videos.\",\"score\":1,\"maxScore\":5},\"paragraphLength\":{\"title\":\"Paragraphs length\",\"description\":\"You are using short paragraphs.\",\"score\":5,\"maxScore\":5,\"error\":0},\"sentenceLength\":{\"title\":\"Sentences length\",\"description\":\"Sentence length is looking great!\",\"score\":9,\"maxScore\":9,\"error\":0},\"passiveVoice\":{\"title\":\"Passive voice\",\"description\":\"You\'re using enough active voice. That\'s great!\",\"score\":9,\"maxScore\":9,\"error\":0},\"transitionWords\":[],\"consecutiveSentences\":{\"title\":\"Consecutive sentences\",\"description\":\"There is enough variety in your sentences. That\'s great!\",\"score\":9,\"maxScore\":9,\"error\":0},\"subheadingsDistribution\":{\"title\":\"Subheading distribution\",\"description\":\"You are not using any subheadings, but your text is short enough and probably doesn\'t need them.\",\"score\":9,\"maxScore\":9,\"error\":0},\"calculateFleschReading\":{\"title\":\"Flesch reading ease\",\"description\":\"The copy scores 77.9 in the test, which is considered fairly easy to read.\",\"score\":9,\"maxScore\":9,\"error\":0},\"errors\":1}}}","canonical_url":null,"og_title":"FB Title","og_description":"FB Description","og_object_type":"default","og_image_type":"default","og_image_url":null,"og_image_width":null,"og_image_height":null,"og_image_custom_url":null,"og_image_custom_fields":null,"og_video":"","og_custom_url":null,"og_article_section":"Article seo section","og_article_tags":"[{\"label\":\"Seo tag\",\"value\":\"Seo tag\"}]","twitter_use_og":"0","twitter_card":"default","twitter_image_type":"default","twitter_image_url":null,"twitter_image_custom_url":null,"twitter_image_custom_fields":null,"twitter_title":null,"twitter_description":null,"seo_score":"60","schema_type":"default","schema_type_options":"{\"article\":{\"articleType\":\"BlogPosting\"},\"course\":{\"name\":\"\",\"description\":\"\",\"provider\":\"\"},\"faq\":{\"pages\":[]},\"product\":{\"reviews\":[]},\"recipe\":{\"ingredients\":[],\"instructions\":[],\"keywords\":[]},\"software\":{\"reviews\":[],\"operatingSystems\":[]},\"webPage\":{\"webPageType\":\"WebPage\"}}","pillar_content":"0","robots_default":"1","robots_noindex":"0","robots_noarchive":"0","robots_nosnippet":"0","robots_nofollow":"0","robots_noimageindex":"0","robots_noodp":"0","robots_notranslate":"0","robots_max_snippet":"-1","robots_max_videopreview":"-1","robots_max_imagepreview":"large","tabs":"{\"tab\":\"social\",\"tab_social\":\"facebook\",\"tab_sidebar\":\"general\",\"tab_modal\":\"general\",\"tab_modal_social\":\"facebook\"}","images":null,"image_scan_date":null,"priority":"default","frequency":"default","videos":null,"video_thumbnail":null,"video_scan_date":null,"local_seo":null,"limit_modified_date":"0","created":"2022-05-17 12:17:01","updated":"2022-05-17 12:17:40"}', true)
            )
        );
    }
    
    public function testSplitTagField()
    {
        if (!defined('OBJECT')) {
            define('OBJECT', 'OBJECT');
        }
        $x = $this->getExternalContentAioseo();
        $this->assertEquals(null, $x->addPlaceholders(null));
        $this->assertEquals('', $x->addPlaceholders(''));
        $this->assertEquals('Content', $x->addPlaceholders('Content'));
        $this->assertEquals(PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_START . '#Content' . PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_END, $x->addPlaceholders('#Content'));
        $this->assertEquals(PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_START . '#post_title' . PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_END . ' ' .
            PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_START . '#separator_sa' . PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_END . ' ' .
            PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_START . '#site_title' . PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_END .
            ' Post title edited',
            $x->addPlaceholders('#post_title #separator_sa #site_title Post title edited'));
        $this->assertEquals(PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_START . '#post_excerpt' . PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_END .
            ' Translation content in the middle ' .
            PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_START . '#separator_sa' . PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_END,
        $x->addPlaceholders('#post_excerpt Translation content in the middle #separator_sa'));
    }

    public function testJoinTagField()
    {
        if (!defined('OBJECT')) {
            define('OBJECT', 'OBJECT');
        }
        $x = $this->getExternalContentAioseo();
        $this->assertNull($x->removePlaceholders(null));
        $this->assertEquals('', $x->removePlaceholders(''));
        $this->assertEquals('#Content', $x->removePlaceholders(PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_START . '#Content' . PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_END));
        $this->assertEquals('#post_title #separator_sa #site_title Post title edited', $x->removePlaceholders(
            PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_START . '#post_title #separator_sa #site_title' . PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_END .
            ' Post title edited'
        ));
        $this->assertEquals('#post_excerpt Translation content in the middle #separator_sa', $x->removePlaceholders(
            PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_START . '#post_excerpt' . PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_END .
            ' Translation content in the middle ' .
            PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_START . '#separator_sa' . PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_END
        ));
    }

    private function getExternalContentAioseo(): ExternalContentAioseo
    {
        return new ExternalContentAioseo(new PlaceholderHelper(), $this->createMock(SiteHelper::class), $this->createMock(SmartlingToCMSDatabaseAccessWrapperInterface::class), $this->createPartialMock(FieldsFilterHelper::class, []));
    }
}
