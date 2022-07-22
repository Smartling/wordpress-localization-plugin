<?php

namespace Smartling\ContentTypes;

use Smartling\Exception\EntityNotFoundException;
use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Models\ExternalData;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class ExternalContentElementor extends ExternalContentAbstract implements ContentTypeModifyingInterface
{
    use LoggerSafeTrait;

    private FieldsFilterHelper $fieldsFilterHelper;
    private SubmissionManager $submissionManager;
    private WordpressFunctionProxyHelper $wpProxy;

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

    public function __construct(FieldsFilterHelper $fieldsFilterHelper, SubmissionManager $submissionManager, WordpressFunctionProxyHelper $wpProxy)
    {
        $this->fieldsFilterHelper = $fieldsFilterHelper;
        $this->submissionManager = $submissionManager;
        $this->wpProxy = $wpProxy;
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

    private function extractContent(array $data, string $previousPrefix = ''): ExternalData {
        $result = new ExternalData();
        foreach ($data as $component) {
            $prefix = $previousPrefix . $component['id'];
            if (is_array($component['elements'])) {
                $result = $result->merge($this->extractContent($component['elements'], $prefix . FieldsFilterHelper::ARRAY_DIVIDER));
                $relatedId = $this->getRelatedImageIdFromElement($component);
                if ($relatedId !== null) {
                    $result = $result->addRelated(['attachment' => $relatedId]);
                }
            }
            if (isset($component['settings'])) {
                foreach ($component['settings'] as $key => $setting) {
                    if (strpos($key, '_') === 0) {
                        continue;
                    }

                    if (is_array($setting)) {
                        foreach ($setting as $id => $option) {
                            if (is_array($option)) {
                                foreach ($option as $optionKey => $optionValue) {
                                    if (strpos($optionKey, '_') === 0) {
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
        $submission->assertHasSource();
        return $this->extractContent($this->getElementorDataFromPostMeta($submission->getSourceId()))->getStrings();
    }

    private function getElementorDataFromPostMeta(int $id)
    {
        return json_decode($this->wpProxy->getPostMeta($id, '_elementor_data', true) ?? '[]', true, 512, JSON_THROW_ON_ERROR);
    }

    public function getMaxVersion(): string
    {
        return '3.6';
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

    public function getRelatedContent(string $contentType, int $id): array
    {
        return $this->extractContent($this->getElementorDataFromPostMeta($id))->getRelated();
    }

    private function getTargetAttachmentId(SubmissionEntity $submission, int $attachmentId): int
    {
        $targetSubmissions = $this->submissionManager->find([
            SubmissionEntity::FIELD_SOURCE_BLOG_ID => $submission->getSourceBlogId(),
            SubmissionEntity::FIELD_SOURCE_ID => $attachmentId,
            SubmissionEntity::FIELD_TARGET_BLOG_ID => $submission->getTargetBlogId(),
        ]);
        switch (count($targetSubmissions)) {
            case 0:
                $this->getLogger()->debug('No submissions found while getting target attachmentId for sourceId=' . $attachmentId);
                break;
            case 1:
                $targetId = $targetSubmissions[0]->getTargetId();
                if ($targetId !== 0) {
                    return $targetId;
                }
                $this->getLogger()->info('Got 0 target attachment id for sourceId=' . $attachmentId);
                break;
            default:
                $this->getLogger()->notice('Found more than one submissions while getting target attachmentId for sourceId=' . $attachmentId);
        }
        throw new EntityNotFoundException();
    }

    private function getRelatedImageIdFromElement(array $element): ?int {
        if ($element['elType'] === 'widget' && $element['widgetType'] === 'image') {
            return $element['settings']['image']['id'] ?? null;
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
                    if (strpos($settingIndex, '_') === 0) {
                        continue;
                    }
                    if (is_array($setting)) {
                        if (array_key_exists('id', $setting) && array_key_exists('url', $setting)) {
                            try {
                                $original[$componentIndex]['settings'][$settingIndex]['id'] = $this->getTargetAttachmentId($submission, $setting['id']);
                            } catch (EntityNotFoundException $e) {
                                $this->getLogger()->info('No target id found, skipped changing id for attachmentId=' . $setting['id']);
                            }
                        } else {
                            foreach ($setting as $optionIndex => $option) {
                                if (is_array($option)) {
                                    foreach ($option as $optionsIndex => $optionValue) {
                                        if (strpos($optionsIndex, '_') === 0) {
                                            continue;
                                        }
                                        $key = implode(FieldsFilterHelper::ARRAY_DIVIDER, [$prefix, $settingIndex, $option['_id'], $optionsIndex]);
                                        $element = $original[$componentIndex]['settings'][$settingIndex][$optionIndex][$optionsIndex];
                                        if (is_array($element) && array_key_exists('id', $element) && array_key_exists('url', $element)) {
                                            try {
                                                $original[$componentIndex]['settings'][$settingIndex][$optionIndex][$optionsIndex]['id'] = $this->getTargetAttachmentId($submission, $element['id']);
                                            } catch (EntityNotFoundException $e) {
                                                $this->getLogger()->info('No target id found, skipped changing id for attachmentId=' . $setting['id']);
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
        }

        return $original;
    }

    public function setContentFields(array $original, array $translation, SubmissionEntity $submission): array
    {
        $translation['meta']['_elementor_data'] = addslashes(json_encode($this->mergeElementorData(
            json_decode($original['meta']['_elementor_data'] ?? '[]', true, 512, JSON_THROW_ON_ERROR),
            $this->fieldsFilterHelper->flattenArray($translation[$this->getPluginId()] ?? []),
            $submission,
        ), JSON_THROW_ON_ERROR));
        unset($translation[$this->getPluginId()]);
        return $translation;
    }
}
