<?php

namespace KPS3\Smartling\Elementor;

use Smartling\Base\ExportedAPI;
use Smartling\Helpers\FieldsFilterHelper;

class ElementorFilter implements RunnableInterface {
    protected array $items = [];

    public static array $allowKeys = array(
        'editor',
        'title',
        'headline',
        'cta-text',
        'tab_title',
        'tab_content',
        'alert_title',
        'alert_description',
        'text',
        'prefix',
        'suffix',
        'address',
        'html',
        'title_text',
        'description_text',
        'caption',
        'anchor',
        'anchor_note',
        'inner_text',
        'inner_text_heading',
        'link_text',
        'shortcode',
        'testimonial_content',
        'testimonial_name',
        'testimonial_job',
        'blockquote_content',
        'author_name',
        'tweet_button_label',
        'user_name',
        'before_text',
        'highlighted_text',
        'rotating_text',
        'after_text',
        'button',
        'button_text',
        'description',
        'label_days',
        'label_hours',
        'label_minutes',
        'label_seconds',
        'message_after_expire',
        'title_text_a',
        'description_text_a',
        'title_text_b',
        'description_text_b',
        'field_options',
        'field_html',
        'field_value',
        'form_name',
        'success_message',
        'error_message',
        'required_field_message',
        'invalid_message',
        'user_placeholder',
        'user_label',
        'password_label',
        'password_placeholder',
        'dropdown_description',
        'price',
        'item_description',
        'period',
        'footer_additional_info',
        'ribbon_title',
        'footer_additional_info',
        'social_counter_notice',
        'heading',
        'follow_description',
        'nothing_found_message',
        'excerpt',
        'author_bio',
        'text_prefix',
        'text_next',
        'string_no_comments',
        'string_one_comment',
        'string_comments',
        'custom_text',
        'prev_label',
        'next_label',
        'custom_text',
        'placeholder',
        'sitemap_title',
        'sitemap_title',
        'sitemap_title',
        'read_more_text',
    );

    public static function register(): void
    {
        $obj = new static();

        $action = is_admin() ? 'admin_init' : 'init';
        add_action($action, static function () use ($obj) {
            $obj->run();
        }, 99);
    }

    public function filterSetup(array $filters): array
    {
        return array_merge($filters, [
            [

                'pattern'       => '^_elementor_data$',
                'action'        => 'localize',
                'serialization' => 'elementor_data',
                'value'         => 'reference',
                'type'          => 'post',
            ],
            [
                'pattern' => '^_elementor_edit_mode$',
                'action'  => 'copy',
            ],
            [

                'pattern' => '^_elementor_page_settings$',
                'action'  => 'copy',
            ],
            [

                'pattern' => '^_elementor_version$',
                'action'  => 'copy',
            ],
            [

                'pattern' => '^_elementor_template_type$',
                'action'  => 'copy',
            ],
            [

                'pattern' => '^_elementor_css$',
                'action'  => 'copy',
            ],

        ]);
    }

    public function run(): void
    {
        add_filter(ExportedAPI::FILTER_SMARTLING_REGISTER_FIELD_FILTER, [$this, 'filterSetup'], 1);

        add_filter(ExportedAPI::FILTER_SMARTLING_METADATA_PROCESS_BEFORE_TRANSLATION, [$this, 'filterElementorData', ], 10, 4);
    }

    /**
     * @see FieldsFilterHelper::passFieldProcessorsBeforeSendFilters()
     * @noinspection PhpUnusedParameterInspection arguments important
     * @param mixed $stringValue
     * @return mixed
     */
    public function filterElementorData($submission, $stringName, $stringValue, $data)
    {
        if (strpos($stringName, 'meta/_elementor_page_settings') !== false
            || in_array($stringName, ['meta/_elementor_edit_mode', 'meta/_elementor_template_type', 'meta/_elementor_version'], true)) {
            return null;
        }

        if ($stringName === 'meta/_elementor_data') {
            $this->items = [];

            $data = json_decode($stringValue, true, 512, JSON_THROW_ON_ERROR);
            $this->extractElementorData($data);
            $this->items = array_filter($this->items);

            return $this->items;
        }

        return $stringValue;
    }

    protected function extractElementorData($data, $prefix = ''): void
    {
        foreach ($data as $components) {
            $myPrefix = $prefix . $components['id'];
            if (isset($components['elements'])) {
                $this->extractElementorData($components['elements'], $myPrefix . '/');
            }
            if (isset($components['settings'])) {
                foreach ($components['settings'] as $key => $setting) {
                    if ($key[0] === '_') {
                        continue;
                    }
                    if (is_array($setting)) {
                        foreach ($setting as $optionID => $option) {
                            if (is_array($option)) {
                                $options = array_filter($option, static function ($k) {
                                    return $k[0] !== '_';
                                }, ARRAY_FILTER_USE_KEY);

                                foreach ($options as $optionKey => $optionValue) {
                                    in_array($optionKey, self::$allowKeys, true) && $this->items[$myPrefix . '/' . $key . '/' . $option['_id'] . '/' . $optionKey] = $optionValue;
                                }
                            } else {
                                in_array($optionID, self::$allowKeys, true) && $this->items[$myPrefix . '/' . $key . '/' . $optionID] = $option;
                            }
                        }
                    } else {
                        in_array($key, self::$allowKeys, true) && $this->items[$myPrefix . '/' . $key] = $setting;
                    }
                }
            }
        }
    }
}
