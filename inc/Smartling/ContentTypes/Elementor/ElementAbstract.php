<?php

namespace Smartling\ContentTypes\Elementor;

use Smartling\Helpers\ArrayHelper;
use Smartling\Models\Content;
use Smartling\Models\RelatedContentInfo;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

abstract class ElementAbstract implements Element {
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

    protected function getIntSettingByKey(string $key, array $settings): ?int
    {
        $setting = $this->getSettingByKey($key, $settings);

        return is_int($setting) ? $setting : null;
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
        string $path,
        SubmissionEntity $submission,
        SubmissionManager $submissionManager,
    ): static {
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
    ): static {
        foreach ($this->elements as $key => $element) {
            if ($element instanceof Element) {
                $this->elements[$key] = $element->setTargetContent(
                    new RelatedContentInfo($info->getInfo()[$this->id] ?? []),
                    $strings[$this->id] ?? $strings[$element->id] ?? [],
                    $submission,
                    $submissionManager,
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
            $this->raw = $this->setRelations($content, $path, $submission, $submissionManager)->toArray();
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
}
