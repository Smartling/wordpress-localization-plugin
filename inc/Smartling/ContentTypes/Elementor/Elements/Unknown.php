<?php

namespace Smartling\ContentTypes\Elementor\Elements;

use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\ContentTypes\Elementor\Element;
use Smartling\ContentTypes\Elementor\ElementFactory;
use Smartling\Helpers\ArrayHelper;
use Smartling\Models\Content;
use Smartling\Models\RelatedContentInfo;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class Unknown implements Element {
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

    public function getIntSettingByKey(string $key, array $settings): ?int
    {
        $setting = $this->getSettingByKey($key, $settings);

        return is_int($setting) ? $setting : null;
    }

    public function getRelated(): RelatedContentInfo
    {
        $return = new RelatedContentInfo();
        $keys = ['background_image/id'];
        foreach ($this->elements as $element) {
            if ($element instanceof Element) {
                $return = $return->include($element->getRelated(), $this->id);
            }
        }

        foreach ($keys as $key) {
            $id = $this->getIntSettingByKey($key, $this->settings);
            if ($id !== null) {
                $return->addContent(new Content($id, ContentTypeHelper::POST_TYPE_ATTACHMENT), $this->id, "settings/$key");
            }
        }

        return $return;
    }

    public function getTranslatableStrings(): array
    {
        $return = [];
        $keys = ['background_image/alt', 'text'];
        foreach ($this->elements as $element) {
            if ($element instanceof Element) {
                $return[] = $element->getTranslatableStrings();
                $stringsFromCommonKeys = $this->getTranslatableStringsByKeys($keys, $element);
                if (count($stringsFromCommonKeys) > 0) {
                    $return[] = [$element->getId() => $stringsFromCommonKeys];
                }
            }
        }
        $return = count($return) > 0 ? (new ArrayHelper())->add(...$return) : $return;

        $return += $this->getTranslatableStringsByKeys($keys);

        return [$this->id => $return];
    }

    public function getSettingByKey(string $key, array $settings): mixed
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

    public function getStringSettingByKey(string $key, array $settings): ?string
    {
        $setting = $this->getSettingByKey($key, $settings);

        return is_string($setting) ? $setting : null;
    }

    public function getTranslatableStringsByKeys(array $keys, Element $element = null): array
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

    public function getType(): string
    {
        return ElementFactory::UNKNOWN_ELEMENT;
    }

    public function setRelations(Content $content,
        string $path,
        SubmissionEntity $submission,
        SubmissionManager $submissionManager,
    ): Element
    {
        $result = clone $this;
        $target = $submissionManager->findTargetBlogSubmission(
            $content->getContentType(),
            $submission->getSourceBlogId(),
            $content->getContentId(),
            $submission->getTargetBlogId(),
        );
        if ($target !== null) {
            $result->raw = array_replace_recursive(
                $result->raw,
                (new ArrayHelper())->structurize([$path => $target->getTargetId()]),
            );
        }

        return $result;
    }

    public function setTargetContent(
        RelatedContentInfo $info,
        array $strings,
        SubmissionEntity $submission,
        SubmissionManager $submissionManager,
    ): Element
    {
        foreach ($this->elements as &$element) {
            if ($element instanceof Element) {
                $element = $element->setTargetContent(
                    new RelatedContentInfo($info->getInfo()[$this->id] ?? []),
                    $strings[$this->id][$element->id] ?? [],
                    $submission,
                    $submissionManager,
                );
            }
        }
        unset ($element);
        foreach ($info->getOwnRelatedContent($this->id) as $path => $content) {
            assert($content instanceof Content);
            $this->raw['elements'] = $this->elements;
            $this->raw = $this->setRelations($content, $path, $submission, $submissionManager)->toArray();
        }
        return new self($this->raw);
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
}
