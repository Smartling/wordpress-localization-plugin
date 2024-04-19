<?php

namespace Smartling\ContentTypes\Elementor\Elements;

use Smartling\ContentTypes\Elementor\Submission;
use Smartling\ContentTypes\ExternalContentElementor;
use Smartling\Models\RelatedContentInfo;
use Smartling\Submissions\SubmissionEntity;

class Tabs extends Unknown {
    public function getType(): string
    {
        return 'tabs';
    }

    public function getTranslatableStrings(): array
    {
        $return = [];
        $knownRepeaterContent = ['tab_title', 'tab_content'];
        foreach ($this->settings['tabs'] ?? [] as $index => $tab) {
            $key = 'tabs/' . ($tab['_id'] ?? $index);
            foreach ($knownRepeaterContent as $field) {
                if (array_key_exists($field, $tab)) {
                    $return[$key][$field] = $tab[$field];
                }
            }
        }

        return [$this->getId() => $return];
    }

    public function setTargetContent(ExternalContentElementor $externalContentElementor, RelatedContentInfo $info, array $strings, Submission $submission,): static
    {
        foreach ($strings[$this->id] ?? [] as $array) {
            if (is_array($array)) {
                foreach ($array as $id => $values) {
                    foreach ($this->settings['tabs'] ?? [] as $index => $tab) {
                        if (($tab['_id'] ?? '') === $id) {
                            foreach ($values as $property => $value) {
                                $this->settings['tabs'][$index][$property] = $value;
                            }
                        }
                    }
                }
            }
        }
        $this->raw['settings'] = $this->settings;

        return new static($this->raw);
    }
}
