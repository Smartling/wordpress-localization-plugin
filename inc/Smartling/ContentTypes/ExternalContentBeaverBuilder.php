<?php

namespace Smartling\ContentTypes;

use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\PluginHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Models\ExternalData;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class ExternalContentBeaverBuilder extends ExternalContentAbstract implements ContentTypeModifyingInterface
{
    use LoggerSafeTrait;

    protected const META_FIELD_NAME = '_fl_builder_data';
    private const META_NODE_PATH_NAME_REGEX = '[0-9a-f]{13}/';
    private const META_NODE_SETTINGS_CHILD_NODE_REGEX = self::META_NODE_SETTINGS_NAME_REGEX . '.+/';
    public const META_NODE_SETTINGS_NAME_REGEX = self::META_NODE_PATH_NAME_REGEX . 'settings/';
    private const META_SETTINGS_NAME = '_fl_builder_data_settings';

    private ContentTypeHelper $contentTypeHelper;

    private array $removeOnUploadFields = [
        'entity' => [
            'post_content',
        ],
        'meta' => [
            self::META_FIELD_NAME,
            self::META_SETTINGS_NAME,
            '_fl_builder_draft',
            '_fl_builder_draft_settings',
            '_fl_builder_history_position',
            '_fl_builder_history_state',
        ]
    ];

    public function __construct(ContentTypeHelper $contentTypeHelper, PluginHelper $pluginHelper, SubmissionManager $submissionManager, WordpressFunctionProxyHelper $wpProxy)
    {
        parent::__construct($pluginHelper, $submissionManager, $wpProxy);
        $this->contentTypeHelper = $contentTypeHelper;
        $this->submissionManager = $submissionManager;
    }

    public function removeUntranslatableFieldsForUpload(array $source): array
    {
        if (array_key_exists(self::META_FIELD_NAME, $source['meta'] ?? [])) {
            foreach ($this->removeOnUploadFields as $removeKey => $removeValue) {
                if (array_key_exists($removeKey, $source)) {
                    foreach ($removeValue as $field) {
                        foreach ($source[$removeKey] as $sourceKey => $sourceValue) {
                            if (preg_match("~$field~", $sourceKey)) {
                                unset($source[$removeKey][$sourceKey]);
                            }
                        }
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

    private function extractContent(array $data): ExternalData {
        $flat = $this->flatten($data);
        $attachmentIds = $this->getAttachmentIds($flat);
        $result = (new ExternalData())->addStrings($this->removeUntranslatable($flat));
        if (count($attachmentIds) > 0) {
            $result = $result->addRelated([ContentTypeHelper::POST_TYPE_ATTACHMENT => $attachmentIds]);
        }

        return $result;
    }

    private function getAttachmentIds(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (preg_match('~^' . self::META_NODE_SETTINGS_NAME_REGEX . 'data/id$~', $key)) {
                $result[] = (int)$value;
            }
        }

        return $result;
    }

    public function getContentFields(SubmissionEntity $submission, bool $raw): array
    {
        return $this->extractContent($this->getDataFromPostMeta($submission->getSourceId()))->getStrings();
    }

    public function getMaxVersion(): string
    {
        return '2.6';
    }

    public function getMinVersion(): string
    {
        return '2.4';
    }

    public function getPluginId(): string
    {
        return 'beaver_builder';
    }

    public function getPluginPaths(): array
    {
        return [
            'bb-plugin/fl-builder.php',
            'beaver-builder-lite-version/fl-builder.php',
        ];
    }

    public function getRelatedContent(string $contentType, int $contentId): array
    {
        return $this->extractContent($this->getDataFromPostMeta($contentId))->getRelated();
    }

    public function setContentFields(array $original, array $translation, SubmissionEntity $submission): array
    {
        $translation['meta'][self::META_FIELD_NAME] = $this->buildData(unserialize($original['meta'][self::META_FIELD_NAME] ?? '') ?: [], $translation[$this->getPluginId()] ?? [], $submission);
        if (array_key_exists(self::META_SETTINGS_NAME, $original['meta'])) {
            $translation['meta'][self::META_SETTINGS_NAME] = $original['meta'][self::META_SETTINGS_NAME];
        }
        unset($translation[$this->getPluginId()]);
        return $translation;
    }

    /**
     * Mutates $original
     */
    private function applyTranslationValues(array $original, array $translation): void
    {
        foreach ($translation as $translationKey => $translationValue) {
            if (array_key_exists('settings', $translationValue) && property_exists($original[$translationKey], 'settings')) {
                $settings = $original[$translationKey]->settings;
                foreach ($translationValue['settings'] as $settingKey => $settingValue) {
                    if (is_scalar($settings->$settingKey)) {
                        settype($settingValue, gettype($settings->$settingKey));
                        $settings->$settingKey = $settingValue;
                    } elseif (is_array($settings->$settingKey) || is_object($settings->$settingKey)) {
                        foreach ($settingValue as $index => $item) {
                            if (is_array($settings->$settingKey)) {
                                $settings->$settingKey[$index] = $this->toStdClass(array_merge((array)$settings->$settingKey[$index], $item));
                            } elseif (is_object($settings->$settingKey)) {
                                $settings->$settingKey->$index = $item;
                            } else {
                                $this->getLogger()->warning(sprintf('Beaver builder buildData encountered unexpected type while traversing, translationKey="%s", settingsKey="%s", dataType="%s"', $translationKey, $settingKey, gettype($settings->$settingKey)));
                            }
                        }
                    } else {
                        $this->getLogger()->warning(sprintf('Beaver builder buildData encountered unexpected condition: settingsKey="%s", settingsType="%s", dataType="%s"', $settingKey, gettype($settings->$settingKey), gettype($settingValue)));
                    }
                }
                $original[$translationKey]->settings = $settings;
            }
        }
    }

    private function buildData(array $original, array $translation, SubmissionEntity $submission): array
    {
        $this->applyTranslationValues($original, $translation);
        $this->replaceRelatedIds($original, $submission);

        return $original;
    }

    /**
     * Mutates $original
     */
    private function replaceRelatedIds(array $original, SubmissionEntity $submission): void
    {
        foreach ($original as $value) {
            if (($value->settings->type ?? '') === 'photo') {
                $value->settings->data->id = $this->getTargetId($submission->getSourceBlogId(), $value->settings->data->id ?? 0, $submission->getTargetBlogId());
            }
        }
    }

    private function toStdClass(array $array): \stdClass
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->toStdClass($value);
            }
        }

        return (object)$array;
    }

    private function flatten(array $array, string $base = '', string $divider = FieldsFilterHelper::ARRAY_DIVIDER): array
    {
        $result = [];
        foreach ($array as $key => $element) {
            $path = '' === $base ? $key : implode($divider, [$base, $key]);
            $result[] = $this->processArrayElement($path, $element, $divider);
        }

        return array_merge(...$result);
    }

    private function processArrayElement(string $path, $value, string $divider): array
    {
        $valueType = gettype($value);
        $result = [];
        switch ($valueType) {
            case 'array':
                $result = $this->flatten($value, $path, $divider);
                break;
            case 'NULL':
            case 'boolean':
            case 'string':
            case 'integer':
            case 'double':
                $result[$path] = (string)$value;
                break;
            case 'object':
                $result = $this->flatten((array)$value, $path, $divider);
                break;
            case 'unknown type':
            case 'resource':
            default:
                $message = vsprintf(
                    'Unsupported type \'%s\' found in scope for translation. Skipped. Contents: \'%s\'.',
                    [$valueType, var_export($value, true)]
                );
                $this->getLogger()->warning($message);
        }

        return $result;
    }

    private function removeUntranslatable(array $data): array
    {
        $result = [];
        $base = self::META_NODE_SETTINGS_NAME_REGEX;
        $heads = [
            '',
            "{$base}list_items/\\d+/"
        ];
        $remove = [
            '^' . self::META_NODE_PATH_NAME_REGEX . 'node$',
            '^' . self::META_NODE_PATH_NAME_REGEX . 'parent$',
            '^' . self::META_NODE_PATH_NAME_REGEX . 'position$',
            '^' . self::META_NODE_PATH_NAME_REGEX . 'type$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . 'align',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . 'animation',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . 'border',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . 'caption',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . 'crop$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . 'click_action$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . 'data/(?!id$|caption$)',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . 'feed_url$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . 'export$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . '(heading|content)_typography',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . '[^/]*_?id$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . 'import$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . '[^/]*_?type',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . 'typography',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . 'layout',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . 'list_(?!items)',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . '[^/]*list_items/\d+/[^/]*padding[^/]*$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . '[^/]*margin[^/]*$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . '[^/]*responsive[^/]*$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . '[^/]*padding[^/]*$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . 'photo_',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . 'separator_style$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . 'show_captions?$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . '[^/]*size[^/]*$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . 'source$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . '[^/]*_?style$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . '[^/]*tag[^/]*$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . 'title_hover$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . '[^/]*_?transition',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . '[^/]*_?type$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . '[^/]*visibility[^/]*$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . '[^/]*width[^/]*$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . '[^/]*bg_[^/]+$',
            '^' . self::META_NODE_SETTINGS_NAME_REGEX . '[^/]*ss_[^/]+$',
            '^' . self::META_NODE_SETTINGS_CHILD_NODE_REGEX . '[^/]*_?color$',
            '^' . self::META_NODE_SETTINGS_CHILD_NODE_REGEX . '[^/]*_family$',
            '^' . self::META_NODE_SETTINGS_CHILD_NODE_REGEX . '[^/]*_?height$',
            '^' . self::META_NODE_SETTINGS_CHILD_NODE_REGEX . '[^/]*_?gradient$',
            '^' . self::META_NODE_SETTINGS_CHILD_NODE_REGEX . '[^/]*_?layout$',
            '^' . self::META_NODE_SETTINGS_CHILD_NODE_REGEX . '[^/]*_?position$',
            '^' . self::META_NODE_SETTINGS_CHILD_NODE_REGEX . '[^/]*_?style$',
            '^' . self::META_NODE_SETTINGS_CHILD_NODE_REGEX . '[^/]*_?target$',
            '^' . self::META_NODE_SETTINGS_CHILD_NODE_REGEX . '[^/]*_?tag$',
            '^' . self::META_NODE_SETTINGS_CHILD_NODE_REGEX . '[^/]*_?transition$',
            '^' . self::META_NODE_SETTINGS_CHILD_NODE_REGEX . '[^/]*_?type$',
            '^' . self::META_NODE_SETTINGS_CHILD_NODE_REGEX . '[^/]*_?unit$',
        ];
        foreach ($heads as $head) {
            foreach ([
                '[^/]*border[^/]*$',
                'class$',
                '[^/]*color[^/]*$',
                '[^/]*container[^/]*$',
                'content_alignment$',
                '[^/]*edge[^/]*$',
                'flrich\d{13}_content$',
                'flrich\d{13}_text$',
                '[^/]*height[^/]*$',
                '[^/]*icon[^/]*$',
                'id$',
                '[^/]*link[^/]*$',
                '[^/]*margin[^/]*$',
                '[^/]*responsive[^/]*$',
                '[^/]*padding[^/]*$',
                '[^/]*size[^/]*$',
                '[^/]*tag[^/]*$',
                'type$',
                '[^/]*visibility[^/]*$',
                '[^/]*width[^/]*$',
                '[^/]*bg_[^/]+$',
                '[^/]*ss_[^/]+$',
            ] as $property) {
                $remove[] = $head . $property;
            }
        }
        foreach ($data as $key => $value) {
            foreach ($remove as $regex) {
                if (0 !== preg_match("~$regex~", $key)) {
                    continue 2;
                }
            }
            $result[$key] = $value;
        }

        unset($result['entity/post_content']);

        return $result;
    }
}
