<?php

namespace Smartling\ContentTypes;

use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\PluginHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Models\ExternalData;
use Smartling\Services\ContentRelationsDiscoveryService;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class ExternalContentElementor extends ExternalContentAbstract implements ContentTypeModifyingInterface
{
    use LoggerSafeTrait;

    public const CONTENT_TYPE_ELEMENTOR_LIBRARY = 'elementor_library';
    protected const META_FIELD_NAME = '_elementor_data';
    private const PROPERTY_TEMPLATE_ID = 'templateID';
    private ContentTypeHelper $contentTypeHelper;
    private FieldsFilterHelper $fieldsFilterHelper;

    private array $removeOnUploadFields = [
        'entity' => [
            'post_content',
        ],
        'meta' => [
            self::META_FIELD_NAME,
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

    public function __construct(
        ContentTypeHelper $contentTypeHelper,
        FieldsFilterHelper $fieldsFilterHelper,
        PluginHelper $pluginHelper,
        SubmissionManager $submissionManager,
        WordpressFunctionProxyHelper $wpProxy
    )
    {
        parent::__construct($pluginHelper, $submissionManager, $wpProxy);
        $this->contentTypeHelper = $contentTypeHelper;
        $this->fieldsFilterHelper = $fieldsFilterHelper;
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

    public function canHandle(string $contentType, ?int $contentId = null): bool
    {
        return parent::canHandle($contentType, $contentId) &&
            $this->contentTypeHelper->isPost($contentType) &&
            $this->getDataFromPostMeta($contentId) !== '';
    }

    private function extractContent(array $data, string $previousPrefix = ''): ExternalData {
        $result = new ExternalData();
        foreach ($data as $component) {
            $prefix = $previousPrefix . $component['id'];
            if (is_array($component['elements'])) {
                $result = $result->merge($this->extractContent($component['elements'], $prefix . FieldsFilterHelper::ARRAY_DIVIDER));
                $related = $this->getRelatedFromElement($component);
                if ($related !== null) {
                    $result = $result->addRelated($related);
                }
            }
            if (isset($component['settings'])) {
                foreach ($component['settings'] as $key => $setting) {
                    if (str_starts_with($key, '_')) {
                        continue;
                    }

                    if (is_array($setting)) {
                        foreach ($setting as $id => $option) {
                            if (is_array($option)) {
                                foreach ($option as $optionKey => $optionValue) {
                                    if (str_starts_with($optionKey, '_')) {
                                        continue;
                                    }

                                    if (in_array($optionKey, $this->translatableFields, true)) {
                                        $result = $result->addStrings([implode(FieldsFilterHelper::ARRAY_DIVIDER, [$prefix, $key, $option['_id'], $optionKey]) => $optionValue]);
                                    }
                                }
                            } else if (in_array($id, $this->translatableFields, true)) {
                                $result = $result->addStrings([implode(FieldsFilterHelper::ARRAY_DIVIDER, [$prefix, $key, $id]) => $option]);
                            }
                        }
                    } else if (in_array($key, $this->translatableFields, true)) {
                        $result = $result->addStrings([$prefix . FieldsFilterHelper::ARRAY_DIVIDER . $key => $setting]);
                    }
                }
            }
        }

        return $result;
    }

    public function getContentFields(SubmissionEntity $submission, bool $raw): array
    {
        return $this->extractContent($this->getElementorDataFromPostMeta($submission->getSourceId()))->getStrings();
    }

    private function getElementorDataFromPostMeta(int $id)
    {
        return json_decode($this->getDataFromPostMeta($id), true, 512, JSON_THROW_ON_ERROR);
    }

    public function getMaxVersion(): string
    {
        return '3.13';
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

    public function getRelatedContent(string $contentType, int $contentId): array
    {
        return $this->extractContent($this->getElementorDataFromPostMeta($contentId))->getRelated();
    }

    private function getRelatedFromElement(array $element): ?array {
        if ($element['elType'] === 'widget' && array_key_exists('widgetType', $element)) {
            switch ($element['widgetType']) {
                case 'global':
                    $id = $element[self::PROPERTY_TEMPLATE_ID] ?? null;
                    if ($id !== null) {
                        return [ContentRelationsDiscoveryService::POST_BASED_PROCESSOR => [$id => $id]];
                    }
                    break;
                case 'image':
                    $id = $element['settings']['image']['id'] ?? null;
                    if ($id !== null) {
                        return [ContentTypeHelper::POST_TYPE_ATTACHMENT => [$id => $id]];
                    }
                    break;
            }
        }

        return null;
    }

    private function mergeElementorData(array $original, array $translation, SubmissionEntity $submission, string $previousPrefix = ''): array
    {
        foreach ($original as $componentIndex => $component) {
            $prefix = $previousPrefix . $component['id'];
            if (array_key_exists('elements', $component)) {
                $original[$componentIndex]['elements'] = $this->mergeElementorData($component['elements'], $translation, $submission, $prefix . FieldsFilterHelper::ARRAY_DIVIDER);
            }
            if (array_key_exists('settings', $component)) {
                foreach($component['settings'] as $settingIndex => $setting) {
                    if (str_starts_with($settingIndex, '_')) {
                        continue;
                    }
                    if (is_array($setting)) {
                        if (array_key_exists('id', $setting) && array_key_exists('url', $setting) && is_int($setting['id'])) {
                            $targetAttachmentId = $this->getTargetId($submission->getSourceBlogId(), $setting['id'], $submission->getTargetBlogId());
                            if ($targetAttachmentId !== null) {
                                $original[$componentIndex]['settings'][$settingIndex]['id'] = $targetAttachmentId;
                            }
                        } else {
                            foreach ($setting as $optionIndex => $option) {
                                if (is_array($option)) {
                                    foreach ($option as $optionsIndex => $optionValue) {
                                        if (str_starts_with($optionsIndex, '_')) {
                                            continue;
                                        }
                                        $key = implode(FieldsFilterHelper::ARRAY_DIVIDER, [$prefix, $settingIndex, $option['_id'], $optionsIndex]);
                                        $element = $original[$componentIndex]['settings'][$settingIndex][$optionIndex][$optionsIndex];
                                        if (is_array($element) && array_key_exists('id', $element) && array_key_exists('url', $element)) {
                                            $targetAttachmentId = $this->getTargetId($submission->getSourceBlogId(), $element['id'], $submission->getTargetBlogId());
                                            if ($targetAttachmentId !== null) {
                                                $original[$componentIndex]['settings'][$settingIndex][$optionIndex][$optionsIndex]['id'] = $targetAttachmentId;
                                            }
                                        } else if (array_key_exists($key, $translation) && in_array($optionsIndex, $this->translatableFields, true)) {
                                            $original[$componentIndex]['settings'][$settingIndex][$optionIndex][$optionsIndex] = $translation[$key];
                                        }
                                    }
                                } else {
                                    $key = implode(FieldsFilterHelper::ARRAY_DIVIDER, [$prefix, $settingIndex, $optionIndex]);
                                    if (array_key_exists($key, $translation) && in_array($optionIndex, $this->translatableFields, true)) {
                                        $original[$componentIndex]['settings'][$settingIndex][$optionIndex] = $translation[$key];
                                    }
                                }
                            }
                        }
                    } else {
                        $key = $prefix . FieldsFilterHelper::ARRAY_DIVIDER . $settingIndex;
                        if (array_key_exists($key, $translation) && in_array($settingIndex, $this->translatableFields, true)) {
                            $original[$componentIndex]['settings'][$settingIndex] = $translation[$key];
                        }
                    }
                }
            }
            if (array_key_exists('elType', $component) && array_key_exists('widgetType', $component) && $component['elType'] === 'widget' && $component['widgetType'] === 'global' && is_int($component[self::PROPERTY_TEMPLATE_ID])) {
                $targetAttachmentId = $this->getTargetId($submission->getSourceBlogId(), $component[self::PROPERTY_TEMPLATE_ID], $submission->getTargetBlogId(), self::CONTENT_TYPE_ELEMENTOR_LIBRARY);
                if ($targetAttachmentId !== null) {
                    $original[$componentIndex][self::PROPERTY_TEMPLATE_ID] = $targetAttachmentId;
                }
            }
        }

        return $original;
    }

    public function setContentFields(array $original, array $translation, SubmissionEntity $submission): array
    {
        $translation['meta'][self::META_FIELD_NAME] = json_encode($this->mergeElementorData(
            json_decode($original['meta'][self::META_FIELD_NAME] ?? '[]', true, 512, JSON_THROW_ON_ERROR),
            $this->fieldsFilterHelper->flattenArray($translation[$this->getPluginId()] ?? []),
            $submission,
        ), JSON_THROW_ON_ERROR);
        unset($translation[$this->getPluginId()]);
        return $translation;
    }
}
