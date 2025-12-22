<?php

namespace Smartling\ContentTypes\Elementor;

use Elementor\Core\DynamicTags\Manager;
use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\ContentTypes\ExternalContentElementor;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Models\Content;
use Smartling\Models\ElementorDynamicTagProcessor;
use Smartling\Models\RelatedContentInfo;
use Smartling\Models\RelatedContentItem;
use Smartling\Submissions\SubmissionEntity;

abstract class ElementAbstract implements Element {
    use LoggerSafeTrait;
    protected const SETTING_KEY_DYNAMIC = '__dynamic__';
    protected const DYNAMIC_INTERNAL_URL = 'internal-url';
    protected const DYNAMIC_POPUP = 'popup';
    protected const DYNAMIC_POST_FEATURED_IMAGE = 'post-featured-image';

    protected array $elements;
    protected string $id;
    protected array $raw;
    protected array $settings;
    protected string $type;

    public function __construct(array $array = [])
    {
        $this->elements = $array['elements'] ?? [];
        $this->id = $array['id'] ?? '';
        $this->raw = $array;
        $this->settings = $array['settings'] ?? [];
        $this->type = $array['elType'] ?? ElementFactory::UNKNOWN_ELEMENT;
    }

    public function fromArray(array $array): static
    {
        return new static($array);
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return RelatedContentItem[]
     */
    public function getRelatedFromDynamic(array $dynamic, string $path): array
    {
        $return = [];
        $dynamicTagsManager = $this->getDynamicTagsManager();
        foreach ($dynamic as $property => $value) {
            try {
                $related = $dynamicTagsManager->parse_tag_text($value, [], function ($id, $name, $settings): ?Content {
                    if (is_array($settings)) {
                        return $this->getKnownDynamicContent($name, $settings);
                    }

                    return null;
                });
                if ($related !== null) {
                    $return[] = new RelatedContentItem($related, $this->id, "$path/$property");
                }
            } catch (\Throwable $e) {
                $this->getLogger()->notice("Failed to get related id for property=$property, tag=$value: {$e->getMessage()}");
                continue;
            }
        }

        return $return;
    }

    protected function getIntSettingByKey(string $key, array $settings): ?int
    {
        $setting = $this->getSettingByKey($key, $settings);
        if (is_int($setting)) {
            return $setting;
        }
        if (is_string($setting)) {
            return ctype_digit($setting) ? (int)$setting : null;
        }

        return null;
    }

    protected function getSettingByKey(string $key, array $settings): mixed
    {
        $parts = explode('/', $key);
        if (count($parts) > 1) {
            $path = array_shift($parts);

            return is_array($settings[$path] ?? '')
                ? $this->getSettingByKey(implode('/', $parts), $settings[$path])
                : null;
        }

        return $settings[$parts[0]] ?? null;
    }

    protected function getStringSettingByKey(string $key, array $settings): ?string
    {
        $setting = $this->getSettingByKey($key, $settings);

        return is_string($setting) ? $setting : null;
    }

    protected function getTranslatableStringsByKeys(array $keys, Element $element = null): array
    {
        if ($element === null) {
            $element = $this;
        }
        $result = [];
        foreach ($keys as $key) {
            $string = $this->getStringSettingByKey($key, $element->settings);
            if ($string !== null) {
                $result[$key] = $string;
            }
        }

        return $result;
    }

    public function setRelations(
        Content $content,
        ExternalContentElementor $externalContentElementor,
        string $path,
        SubmissionEntity $submission,
    ): static {
        $arrayHelper = new ArrayHelper();
        $result = clone $this;

        $wpProxy = $externalContentElementor->getWpProxy();
        if ($content->getType() === ContentTypeHelper::CONTENT_TYPE_POST) {
            $contentType = $wpProxy->get_post_type($content->getId());
        } elseif ($content->getType() === ContentTypeHelper::CONTENT_TYPE_TAXONOMY) {
            $term = $wpProxy->getTerm($content->getId());
            $contentType = (is_array($term) && isset($term['taxonomy'])) ? $term['taxonomy'] : $content->getType();
        } else {
            $contentType = $content->getType();
        }

        if ($contentType === false) {
            $this->getLogger()->debug("Unable to get content type for contentId={$content->getId()}, proceeding with unknown type");
            $contentType = ContentTypeHelper::CONTENT_TYPE_UNKNOWN;
        }
        $targetId = $externalContentElementor->getTargetId(
            $submission->getSourceBlogId(),
            $content->getId(),
            $submission->getTargetBlogId(),
            $contentType,
        );
        if ($targetId !== null) {
            if (is_string($this->getSettingByKey($path, $this->raw ?? []))) {
                $targetId = (string)$targetId;
            }
            $result->raw = array_replace_recursive(
                $result->raw,
                $arrayHelper->structurize([$path => $this->isDynamicProperty($path)
                    ? $this->replaceDynamicTagSetting($arrayHelper->flatten($result->raw)[$path] ?? '', (string)$targetId)
                    : $targetId
                ]),
            );
        }

        return $result;
    }

    public function setTargetContent(
        ExternalContentElementor $externalContentElementor,
        RelatedContentInfo $info,
        array $strings,
        SubmissionEntity $submission,
    ): static {
        foreach ($this->elements as $key => $element) {
            if ($element instanceof Element) {
                $this->elements[$key] = $element->setTargetContent(
                    $externalContentElementor,
                    new RelatedContentInfo($info->getInfo()[$this->id] ?? []),
                    $strings[$this->id] ?? $strings[$element->id] ?? [],
                    $submission,
                );
            }
        }
        foreach ($strings[$this->id] ?? [] as $path => $string) {
            if (!is_array($string)) {
                $this->settings[$path] = $string;
            }
        }
        if (count($this->settings) > 0) {
            $this->raw['settings'] = $this->settings;
        }
        $this->raw['elements'] = $this->elements;
        foreach ($info->getOwnRelatedContent($this->id) as $path => $content) {
            assert($content instanceof Content);
            $this->raw = $this->setRelations($content, $externalContentElementor, $path, $submission)->toArray();
        }

        if (is_array($this->settings[self::SETTING_KEY_DYNAMIC] ?? false)) {
            foreach ($this->settings[self::SETTING_KEY_DYNAMIC] as $index => $tag) {
                $manager = $this->getDynamicTagsManager();
                $processor = $this->getDynamicTagProcessor($manager->tag_text_to_tag_data($tag)['name'] ?? '');
                if ($processor !== null) {
                    $this->getLogger()->debug("Got processor for $tag (id=$this->id}, manager=" . $manager::class);
                    foreach ($this->getRelatedFromDynamic($this->settings[self::SETTING_KEY_DYNAMIC], $index) as $content) {
                        $this->raw = $this->setRelations(
                            $content->getContent(),
                            $externalContentElementor,
                            'settings/' . self::SETTING_KEY_DYNAMIC . "/$index",
                            $submission,
                        )->toArray();
                    }
                }
            }
        }

        return new static($this->raw);
    }

    public function toArray(): array
    {
        foreach ($this->raw['elements'] ?? [] as $index => $element) {
            if ($element instanceof Element) {
                $this->raw['elements'][$index] = $element->toArray();
            }
        }

        return $this->raw;
    }

    public function isDynamicProperty(string $path): bool
    {
        return str_contains($path, self::SETTING_KEY_DYNAMIC);
    }

    public function getDynamicTagsManager(): DynamicTagsManagerShim|Manager
    {
        if (class_exists(Manager::class)) {
            return new Manager();
        }
        if (!defined('WP_PLUGIN_DIR')) {
            $this->getLogger()->notice('Not in WordPress environment, will use shim for Elementor dynamic tags manager');
            return new DynamicTagsManagerShim();
        }
        $managerPath = WP_PLUGIN_DIR . '/elementor/core/dynamic-tags/manager.php';
        if (file_exists($managerPath)) {
            try {
                require_once $managerPath;
                return new Manager();
            } catch (\Throwable $e) {
                $this->getLogger()->notice('Unable to initialize Elementor dynamic tags manager, will use shim: ' . $e->getMessage());
            }
        }

        return new DynamicTagsManagerShim();
    }

    private function getDynamicTagProcessor(string $name): ?ElementorDynamicTagProcessor
    {
        return match ($name) {
            self::DYNAMIC_INTERNAL_URL => new ElementorDynamicTagProcessor('settings/post_id'),
            self::DYNAMIC_POPUP => new ElementorDynamicTagProcessor('settings/' . self::DYNAMIC_POPUP),
            self::DYNAMIC_POST_FEATURED_IMAGE => new ElementorDynamicTagProcessor(
                'settings/fallback/id',
                fn(string $value) => (int)$value,
            ),
            default => null,
        };
    }

    // $value must be string to be converted back to tag
    public function replaceDynamicTagSetting(string $tag, string $value): string
    {
        $dynamicTagsManager = $this->getDynamicTagsManager();
        try {
            $tagData = $dynamicTagsManager->tag_text_to_tag_data($tag);
        } catch (\Throwable $e) {
            $this->getLogger()->warning("Unable to convert Elementor tagText=\"$tag\" to array, value=\"$value\": {$e->getMessage()}");
            return $tag;
        }
        $processor = $this->getDynamicTagProcessor($tagData['name'] ?? '');
        if ($processor === null) {
            $this->getLogger()->warning("Unknown Elementor tag encountered, skipping replacement, tagText=\"$tag\"");
            return $tag;
        }
        $arrayHelper = new ArrayHelper();
        $tagData = $arrayHelper->setValue(
            $tagData,
            $processor->getPath(),
            $processor->getCallable() === null ? $value : $processor->getCallable()($value),
            '/',
        );
        try {
            $tagText = $dynamicTagsManager->tag_data_to_tag_text(...array_values($tagData));
            if ($tagText === '') {
                $this->getLogger()->debug('No tag text returned by manager, fallback tag text creation');
                $tagText = sprintf(
                    '[%1$s id="%2$s" name="%3$s" settings="%4$s"]',
                    'elementor-tag',
                    $tagData['id'] ?? '',
                    $tagData['name'] ?? '',
                    urlencode(json_encode($tagData['settings'] ?? [], JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT)),
                );
            }

            return $tagText;
        } catch (\Throwable $e) {
            $this->getLogger()->warning("Unable to convert Elementor tag data to text: {$e->getMessage()}");
        }

        return $tag;
    }

    protected function getKnownDynamicContent(string $name, array $settings): ?Content
    {
        if ($name === self::DYNAMIC_POPUP && array_key_exists(self::DYNAMIC_POPUP, $settings)) {
            return new Content((int)$settings[self::DYNAMIC_POPUP], ContentTypeHelper::CONTENT_TYPE_UNKNOWN);
        }
        if ($name === self::DYNAMIC_INTERNAL_URL && ($settings['type'] ?? '') === 'post' && array_key_exists('post_id', $settings)) {
            return new Content((int)$settings['post_id'], ContentTypeHelper::CONTENT_TYPE_POST);
        }
        if ($name === self::DYNAMIC_POST_FEATURED_IMAGE && array_key_exists('fallback', $settings) && array_key_exists('id', $settings['fallback'])) {
            return new Content((int)$settings['fallback']['id'], ContentTypeHelper::POST_TYPE_ATTACHMENT);
        }

        return null;
    }
}
