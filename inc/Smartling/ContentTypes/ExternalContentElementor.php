<?php

namespace Smartling\ContentTypes;

use Elementor\Core\Documents_Manager;
use Elementor\Core\DynamicTags\Manager;
use Smartling\Base\ExportedAPI;
use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\PluginHelper;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Helpers\WordpressLinkHelper;
use Smartling\Models\ExternalData;
use Smartling\Services\ContentRelationsDiscoveryService;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class ExternalContentElementor extends ExternalContentAbstract implements ContentTypeModifyingInterface
{
    use LoggerSafeTrait;

    public const CONTENT_TYPE_ELEMENTOR_LIBRARY = 'elementor_library';
    private const DYNAMIC = '__dynamic__';
    protected const META_FIELD_NAME = '_elementor_data';
    private const POPUP = 'popup';
    private const PROPERTY_TEMPLATE_ID = 'templateID';

    private array $copyFields = [
        '_elementor_controls_usage',
        '_elementor_css',
        '_elementor_edit_mode',
        '_elementor_page_assets',
        '_elementor_pro_version',
        '_elementor_template_type',
        '_elementor_version',
    ];

    private array $removeOnUploadFields = [
        'entity' => [
            'post_content',
        ],
        'meta' => [
            self::META_FIELD_NAME,
        ]
    ];

    private array $translatableFields = [
        'address',
        'after_text',
        'alert_description',
        'alert_title',
        'alt',
        'anchor_note',
        'author_bio',
        'author_name',
        'before_text',
        'blockquote_content',
        'button',
        'button_text',
        'caption',
        'content',
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

    private ?Manager $dynamicTagsManager = null;

    public function __construct(
        private ContentTypeHelper $contentTypeHelper,
        private FieldsFilterHelper $fieldsFilterHelper,
        PluginHelper $pluginHelper,
        private SiteHelper $siteHelper,
        SubmissionManager $submissionManager,
        WordpressFunctionProxyHelper $wpProxy,
        private WordpressLinkHelper $wpLinkHelper,
    )
    {
        add_action(ExportedAPI::ACTION_AFTER_TARGET_METADATA_WRITTEN, [$this, 'afterMetaWritten']);
        parent::__construct($pluginHelper, $submissionManager, $wpProxy);
        try {
            require_once WP_PLUGIN_DIR . '/elementor/core/dynamic-tags/manager.php';
            $this->dynamicTagsManager = new Manager();
        } catch (\Throwable $e) {
            $this->getLogger()->notice('Unable to initialize Elementor dynamic tags manager, Elementor tags processing not available: ' . $e->getMessage());
        }
    }

    public function afterMetaWritten(SubmissionEntity $submission): void
    {
        if ($submission->getTargetId() === 0) {
            $this->getLogger()->debug('Processing Elementor after meta written hook aborted, targetId=0');
            return;
        }
        $this->siteHelper->withBlog($submission->getTargetBlogId(), function () use ($submission) {
            $originalUserId = get_current_user_id();
            wp_set_current_user(1, 'smartling');
            $supportLevel = $this->getSupportLevel($submission->getContentType(), $submission->getTargetId());
            $this->getLogger()->debug(sprintf('Processing Elementor after content written hook, contentType=%s, sourceBlogId=%d, sourceId=%d, submissionId=%d, targetBlogId=%d, targetId=%d, supportLevel=%s', $submission->getContentType(), $submission->getSourceBlogId(), $submission->getSourceId(), $submission->getId(), $submission->getTargetBlogId(), $submission->getTargetId(), $supportLevel));
            if ($supportLevel !== self::NOT_SUPPORTED) {
                try {
                    require_once WP_PLUGIN_DIR . '/elementor/core/documents-manager.php';
                    $meta = $this->getDataFromPostMeta($submission->getTargetId());
                    $post = $this->wpProxy->get_post($submission->getTargetId());
                    $manager = new Documents_Manager();
                    do_action('elementor/documents/register', $manager);
                    $document = $manager->get($submission->getTargetId());
                    if ($document === false) {
                        $this->getLogger()->notice('Could not get document');
                    } else {
                        $this->getLogger()->debug('Document is ' . get_class($document));
                    }
                    if (!$document->is_built_with_elementor()) {
                        $this->getLogger()->notice('Document is not built with elementor. Meta: ' . json_encode($meta) . ', mode: ' . $this->wpProxy->getPostMeta($submission->getTargetId(), '_elementor_edit_mode', true));
                    }
                    if (!$document->is_editable_by_current_user()) {
                        $this->getLogger()->notice('Document is not editable by current user');
                        $this->elDebug($submission->getTargetId());
                    }

                    /** @noinspection PhpParamsInspection */
                    $manager->ajax_save([
                        'editor_post_id' => $submission->getTargetId(),
                        'elements' => json_decode($this->getDataFromPostMeta($submission->getTargetId()),
                            true,
                            512,
                            JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT),
                        'status' => $post->post_status,
                    ]);
                } catch (\Throwable $e) {
                    $this->getLogger()->notice(sprintf("Unable to do Elementor save actions for contentType=%s, submissionId=%d, targetBlogId=%d, targetId=%d: %s (%s), post: %s", $submission->getContentType(), $submission->getId(), $submission->getTargetBlogId(), $submission->getTargetId(), $e->getMessage(), $e->getTraceAsString(), json_encode($post->to_array())));
                } finally {
                    wp_set_current_user($originalUserId);
                }
            }
        });
    }

    public function removeUntranslatableFieldsForUpload(array $source): array
    {
        if (array_key_exists(self::META_FIELD_NAME, $source['meta'] ?? [])) {
            $this->getLogger()->info('Detected elementor data, removing post content and elementor related meta fields');
            foreach (array_merge_recursive(['meta' => $this->copyFields], $this->removeOnUploadFields) as $key => $value) {
                if (array_key_exists($key, $source)) {
                    foreach ($value as $field) {
                        unset($source[$key][$field]);
                    }
                }
            }
        }

        return $source;
    }

    public function getSupportLevel(string $contentType, ?int $contentId = null): string
    {
        if ($this->contentTypeHelper->isPost($contentType) && $this->getDataFromPostMeta($contentId) !== '') {
            return parent::getSupportLevel($contentType, $contentId);
        }
        return self::NOT_SUPPORTED;
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
                    if ($key !== self::DYNAMIC && str_starts_with($key, '_')) {
                        continue;
                    }

                    if (is_array($setting)) {
                        $result = $result->merge($this->getRelatedFromSetting($setting));
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
        return '3.18';
    }

    public function getMinVersion(): string
    {
        return '3.4';
    }

    public function getPluginId(): string
    {
        return 'elementor';
    }

    public function getPluginPaths(): array
    {
        return ['elementor/elementor.php'];
    }

    public function getRelatedContent(string $contentType, int $contentId): array
    {
        return $this->extractContent($this->getElementorDataFromPostMeta($contentId))->getRelated();
    }

    private function getRelatedFromElement(array $element): ?array {
        if (($element['elType'] ?? '') === 'widget' && ($element['widgetType'] ?? '') === 'global') {
            $id = $element[self::PROPERTY_TEMPLATE_ID] ?? null;
            if ($id !== null) {
                return [ContentRelationsDiscoveryService::POST_BASED_PROCESSOR => [$id]];
            }
        }

        return null;
    }

    private function getRelatedFromSetting(array $setting): ExternalData {
        if (($setting['source'] ?? '') === 'library' && ($setting['id'] ?? '') !== '') {
            return new ExternalData([], [ContentTypeHelper::POST_TYPE_ATTACHMENT => [$setting['id']]]);
        }
        if (($setting['library'] ?? '') === 'svg' && is_int($setting['value']['id'] ?? '')) {
            return new ExternalData([], [ContentTypeHelper::POST_TYPE_ATTACHMENT => [$setting['value']['id']]]);
        }
        if (is_int($setting['selected_icon']['value']['id'] ?? '')) {
            return new ExternalData([], [ContentTypeHelper::POST_TYPE_ATTACHMENT => [$setting['selected_icon']['value']['id']]]);
        }

        $result = new ExternalData();
        foreach ($setting as $value) {
            if (is_array($value)) {
                $result = $result->merge($this->getRelatedFromSetting($value));
            } elseif (is_string($value)) {
                $relatedId = $this->getPopupId($value);
                if ($relatedId !== null) {
                    $detectedType = $this->wpProxy->get_post_type($relatedId);
                    if (is_string($detectedType)) {
                        $relatedType = ContentTypeHelper::POST_TYPE_ATTACHMENT;
                        if ($this->contentTypeHelper->isPost($detectedType)) {
                            $relatedType = ContentRelationsDiscoveryService::POST_BASED_PROCESSOR;
                        } elseif ($this->contentTypeHelper->isTaxonomy($detectedType)) {
                            $relatedType = ContentRelationsDiscoveryService::TERM_BASED_PROCESSOR;
                        }
                        $result = $result->addRelated([$relatedType => [$relatedId]]);
                    } else {
                        $this->getLogger()->debug("Skip adding relatedId=$relatedId, postType=$detectedType");
                    }
                }
            }
        }

        return $result;
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
                    if ($settingIndex !== self::DYNAMIC && str_starts_with($settingIndex, '_')) {
                        continue;
                    }
                    if (is_array($setting)) {
                        if (array_key_exists('url', $setting)) {
                            if (array_key_exists('id', $setting) && is_int($setting['id']) && ($setting['source'] ?? '') === 'library') {
                                $targetAttachmentId = $this->getTargetId($submission->getSourceBlogId(), $setting['id'], $submission->getTargetBlogId());
                                if ($targetAttachmentId !== null) {
                                    $original[$componentIndex]['settings'][$settingIndex]['id'] = $targetAttachmentId;
                                }
                            }
                            $newPath = $this->wpLinkHelper->getTargetBlogLink($setting['url'], $submission->getTargetBlogId());
                            if ($newPath !== null) {
                                $original[$componentIndex]['settings'][$settingIndex]['url'] = $newPath;
                            }
                        } elseif (($setting['library'] ?? '') === 'svg' && array_key_exists('value', $setting)) {
                            if (array_key_exists('id', $setting['value'])) {
                                $targetAttachmentId = $this->getTargetId($submission->getSourceBlogId(), $setting['value']['id'], $submission->getTargetBlogId());
                                if ($targetAttachmentId !== null) {
                                    $original[$componentIndex]['settings'][$settingIndex]['value']['id'] = $targetAttachmentId;
                                }
                            }
                            if (array_key_exists('url', $setting['value'])) {
                                $newPath = $this->wpLinkHelper->getTargetBlogLink($setting['value']['url'], $submission->getTargetBlogId());
                                if ($newPath !== null) {
                                    $original[$componentIndex]['settings'][$settingIndex]['value']['url'] = $newPath;
                                }
                            }
                        } else {
                            foreach ($setting as $optionIndex => $option) {
                                if (is_array($option)) {
                                    if (($option['selected_icon']['value']['id'] ?? 0) !== 0) {
                                        $targetAttachmentId = $this->getTargetId($submission->getSourceBlogId(), $option['selected_icon']['value']['id'] ?? 0, $submission->getTargetBlogId());
                                        if ($targetAttachmentId !== null) {
                                            $original[$componentIndex]['settings'][$settingIndex][$optionIndex]['selected_icon']['value']['id'] = $targetAttachmentId;
                                        }
                                    }
                                    foreach ($option as $optionsIndex => $optionValue) {
                                        if (!array_key_exists('_id', $option) || str_starts_with($optionsIndex, '_')) {
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
                                } elseif ($this->dynamicTagsManager !== null && is_string($option) && str_starts_with($option, '[' . Manager::TAG_LABEL)) {
                                    $this->getLogger()->debug("Processing Elementor tag $option");
                                    $popupId = $this->getPopupId($option);
                                    if ($popupId !== null) {
                                        try {
                                            $tagData = $this->dynamicTagsManager->tag_text_to_tag_data($option);
                                        } catch (\Throwable $e) {
                                            $this->getLogger()->warning("Unable to convert Elementor tagText=\"$option\" to array, popupId=$popupId: {$e->getMessage()}");
                                            continue;
                                        }
                                        $targetSubmission = $this->submissionManager->findOne([
                                            SubmissionEntity::FIELD_SOURCE_BLOG_ID => $submission->getSourceBlogId(),
                                            SubmissionEntity::FIELD_SOURCE_ID => $popupId,
                                            SubmissionEntity::FIELD_TARGET_BLOG_ID => $submission->getTargetBlogId(),
                                        ]);
                                        if ($targetSubmission !== null) {
                                            $this->getLogger()->debug("Replacing popupId=$popupId with targetId={$submission->getTargetId()}");
                                            try {
                                                $tagData['settings']['popup'] = (string)$targetSubmission->getTargetId();
                                                $tagText = $this->dynamicTagsManager->tag_data_to_tag_text(...array_values($tagData));
                                                if ($tagText === '') {
                                                    $this->getLogger()->info('No tag text returned by manager, fallback tag text creation');
                                                    $tagText = sprintf('[%1$s id="%2$s" name="%3$s" settings="%4$s"]',
                                                        Manager::TAG_LABEL,
                                                        $tagData['id'] ?? '',
                                                        $tagData['name'] ?? '',
                                                        urlencode(json_encode($tagData['settings'] ?? [], JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT)));
                                                }
                                                $original[$componentIndex]['settings'][$settingIndex][$optionIndex] = $tagText;
                                            } catch (\Throwable $e) {
                                                $this->getLogger()->warning("Unable to apply relation sourceId=$popupId, targetId={$targetSubmission->getTargetId()}: {$e->getMessage()}");
                                            }
                                        } else {
                                            $this->getLogger()->debug("No target submission exists");
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
        if (array_key_exists('meta', $original)) {
            foreach ($this->copyFields as $field) {
                if (array_key_exists($field, $original['meta'])) {
                    $value = $original['meta'][$field];
                    $translation['meta'][$field] = is_string($value) ? $this->wpProxy->maybe_unserialize($value) : $value;
                }
            }
        }
        $translation['meta'][self::META_FIELD_NAME] = json_encode($this->mergeElementorData(
            json_decode($original['meta'][self::META_FIELD_NAME] ?? '[]', true, 512, JSON_THROW_ON_ERROR),
            $this->fieldsFilterHelper->flattenArray($translation[$this->getPluginId()] ?? []),
            $submission,
        ), JSON_THROW_ON_ERROR);
        unset($translation[$this->getPluginId()]);
        return $translation;
    }

    private function elDebug(int $postId): void
    {
        $this->getLogger()->debug('Elementor debug for postId=' . $postId);
        $this->is_current_user_can_edit($postId);
    }

    private function is_current_user_can_edit(int $post_id): bool
    {
        $post = get_post($post_id);

        if (!$post) {
            $this->getLogger()->debug('No post');
            return false;
        }

        if ('trash' === get_post_status($post->ID)) {
            $this->getLogger()->debug('Post is trash');
            return false;
        }

        if (!$this->is_current_user_can_edit_post_type($post->post_type)) {
            return false;
        }

        $post_type_object = get_post_type_object($post->post_type);

        if (!isset($post_type_object->cap->edit_post)) {
            $this->getLogger()->debug('Edit post capability not set for ' . json_encode($post_type_object));
            return false;
        }

        $edit_cap = $post_type_object->cap->edit_post;
        if (!current_user_can($edit_cap, $post->ID)) {
            $this->getLogger()->debug('Current user cannot process ' . json_encode($post_type_object) . '(2)');
            return false;
        }

        if ((int)get_option('page_for_posts') === $post->ID) {
            $this->getLogger()->debug('Page for posts is equal to post ID (?)');
            return false;
        }

        return true;
    }

    private function is_current_user_can_edit_post_type(string $post_type): bool
    {
        if (!$this->is_current_user_in_editing_black_list()) {
            $this->getLogger()->debug('User in editing black list');
            return false;
        }

        if (!$this->is_post_type_support($post_type)) {
            $this->getLogger()->debug('Post type not supported');
            return false;
        }

        $post_type_object = get_post_type_object($post_type);

        $user = wp_get_current_user();
        $this->getLogger()->debug('Current user is ' . json_encode($user));

        if (!current_user_can($post_type_object->cap->edit_posts)) {
            $this->getLogger()->debug('Current user cannot process ' . json_encode($post_type_object));
            return false;
        }

        return true;
    }

    private function is_current_user_in_editing_black_list(): bool
    {
        $user = wp_get_current_user();
        $exclude_roles = get_option('elementor_exclude_user_roles', []);

        $compare_roles = array_intersect($user->roles, $exclude_roles);
        if (!empty($compare_roles)) {
            return false;
        }

        return true;
    }

    private function is_post_type_support(string $post_type): bool
    {
        if (!post_type_exists($post_type)) {
            $this->getLogger()->debug('Post type does not exist');
            return false;
        }

        if (!post_type_supports($post_type, 'elementor')) {
            $this->getLogger()->debug('Post type does not support Elementor');
            return false;
        }

        return true;
    }

    private function getPopupId(string $value): ?int
    {
        $relatedId = null;
        try {
            if ($this->dynamicTagsManager !== null && str_starts_with($value, '[' . Manager::TAG_LABEL)) {
                $relatedId = $this->dynamicTagsManager->parse_tag_text($value, [], function ($id, $name, $settings) {
                    if (is_array($settings) && array_key_exists(self::POPUP, $settings)) {
                        return (int)$settings[self::POPUP];
                    }

                    return null;
                });
            }
        } catch (\Throwable $e) {
            $this->getLogger()->notice('Failed to process Elementor tag ' . $value . ': ' . $e->getMessage());
        }

        return $relatedId;
    }
}
