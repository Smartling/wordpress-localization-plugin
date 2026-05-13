<?php

namespace Smartling\ContentTypes\Elementor;

use Smartling\Models\RelatedContentInfo;
use Smartling\Submissions\SubmissionEntity;

abstract class ElementAbstract4 extends ElementAbstract
{
    protected function extractTypedValue(mixed $value): ?string
    {
        if (!is_array($value) || !isset($value['$$type'])) {
            return null;
        }
        return match ($value['$$type']) {
            'string' => isset($value['value']) && is_string($value['value']) ? $value['value'] : null,
            'html-v3' => isset($value['value']['content']['value']) && is_string($value['value']['content']['value'])
                ? $value['value']['content']['value']
                : null,
            default => null,
        };
    }

    public function setTargetContent(
        ExternalContentElementorInterface $externalContentElementor,
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

        foreach ($strings[$this->id] ?? [] as $settingKey => $string) {
            if (!is_array($string)) {
                $this->setTypedSettingValue($settingKey, $string);
            }
        }

        if (count($this->settings) > 0) {
            $this->raw['settings'] = $this->settings;
        }
        $this->raw['elements'] = $this->elements;

        foreach ($info->getOwnRelatedContent($this->id) as $path => $content) {
            $this->raw = $this->setRelations($content, $externalContentElementor, $path, $submission)->toArray();
        }

        return new static($this->raw);
    }

    private function setTypedSettingValue(string $settingKey, string $value): void
    {
        $existing = $this->settings[$settingKey] ?? null;
        if (!is_array($existing) || !isset($existing['$$type'])) {
            $this->settings[$settingKey] = $value;
            return;
        }
        match ($existing['$$type']) {
            'string' => $this->settings[$settingKey]['value'] = $value,
            'html-v3' => $this->settings[$settingKey]['value']['content']['value'] = $value,
            default => null,
        };
    }
}
