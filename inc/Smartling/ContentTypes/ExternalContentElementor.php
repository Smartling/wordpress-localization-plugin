<?php

namespace Smartling\ContentTypes;

use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Submissions\SubmissionEntity;

class ExternalContentElementor extends ExternalContentAbstract implements ContentTypeModifyingInterface
{
    private WordpressFunctionProxyHelper $wpProxy;

    public function __construct(WordpressFunctionProxyHelper $wpProxy)
    {
        $this->wpProxy = $wpProxy;
    }

    private array $removeOnUploadFields = [
        'entity' => [
            'post_content',
        ],
        'meta' => [
            '_elementor_data',
            '_elementor_edit_mode',
            '_elementor_template_type',
            '_elementor_version',
        ]
    ];

    private array $translatableFields = [
        'address',
        'after_text',
        'alert_description',
        'alert_title',
        'anchor',
        'anchor_note',
        'author_bio',
        'author_name',
        'before_text',
        'blockquote_content',
        'button',
        'button_text',
        'caption',
        'cta-text',
        'custom_text',
        'custom_text',
        'description',
        'description_text',
        'description_text_a',
        'description_text_b',
        'dropdown_description',
        'editor',
        'error_message',
        'excerpt',
        'field_html',
        'field_options',
        'field_value',
        'follow_description',
        'footer_additional_info',
        'footer_additional_info',
        'form_name',
        'heading',
        'headline',
        'highlighted_text',
        'html',
        'inner_text',
        'inner_text_heading',
        'invalid_message',
        'item_description',
        'label_days',
        'label_hours',
        'label_minutes',
        'label_seconds',
        'link_text',
        'message_after_expire',
        'next_label',
        'nothing_found_message',
        'password_label',
        'password_placeholder',
        'period',
        'placeholder',
        'prefix',
        'prev_label',
        'price',
        'read_more_text',
        'required_field_message',
        'ribbon_title',
        'rotating_text',
        'shortcode',
        'sitemap_title',
        'sitemap_title',
        'sitemap_title',
        'social_counter_notice',
        'string_comments',
        'string_no_comments',
        'string_one_comment',
        'success_message',
        'suffix',
        'tab_content',
        'tab_title',
        'testimonial_content',
        'testimonial_job',
        'testimonial_name',
        'text',
        'text_next',
        'text_prefix',
        'title',
        'title_text',
        'title_text_a',
        'title_text_b',
        'tweet_button_label',
        'user_label',
        'user_name',
        'user_placeholder',
    ];

    private function extractElementorData(array $data, string $prefix = ''): array {
        $result = [];
        foreach ($data as $component) {
            $prefix .= $component['id'];
            if (isset($component['elements'])) {
                $result = array_merge($result, $this->extractElementorData($component['elements'], $prefix . '/'));
            }
            if (isset($component['settings'])) {
                foreach ($component['settings'] as $key => $setting) {
                    if (strpos($key, '_') === 0) {
                        continue;
                    }
                    if (is_array($setting)) {
                        foreach ($setting as $id => $option) {
                            if (is_array($option)) {
                                $options = array_filter($option, static function ($k) {
                                    return strpos($k, '_') !== 0;
                                }, ARRAY_FILTER_USE_KEY);

                                foreach ($options as $optionKey => $optionValue) {
                                    if (in_array($optionKey, $this->translatableFields, true)) {
                                        $result[$prefix . '/' . $key . '/' . $option['_id'] . '/' . $optionKey] = $optionValue;
                                    }
                                }
                            } else if (in_array($id, $this->translatableFields, true)) {
                                $result[$prefix . '/' . $key . '/' . $id] = $option;
                            }
                        }
                    } else if (in_array($key, $this->translatableFields, true)) {
                        $result[$prefix . '/' . $key] = $setting;
                    }
                }
            }
        }

        return $result;
    }

    public function getContentFields(SubmissionEntity $submission, bool $raw): array
    {
        $submission->assertHasSource();
        return $this->extractElementorData(json_decode($this->wpProxy->getPostMeta($submission->getSourceId(), '_elementor_data', true) ?? '[]', true, 512, JSON_THROW_ON_ERROR));
    }

    public function getMaxVersion(): string
    {
        return '3.7';
    }

    public function getMinVersion(): string
    {
        return '3.4';
    }

    public function getPluginId(): string
    {
        return 'elementor';
    }

    public function getPluginPath(): string
    {
        return 'elementor/elementor.php';
    }

    public function setContentFields(array $original, array $translation, SubmissionEntity $submission): array
    {
        $translation['meta']['_elementor_data'] = json_encode($translation[$this->getPluginId()], JSON_THROW_ON_ERROR);
        unset($translation[$this->getPluginId()]);
        return $translation;
    }

    public function alterContentFieldsForUpload(array $source): array
    {
        foreach ($this->removeOnUploadFields as $key => $value) {
            if (array_key_exists($key, $source)) {
                foreach ($value as $field) {
                    unset($source[$key][$field]);
                }
            }
        }

        return $source;
    }
}
