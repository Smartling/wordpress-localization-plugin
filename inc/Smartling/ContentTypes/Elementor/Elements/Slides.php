<?php

namespace Smartling\ContentTypes\Elementor\Elements;

use Smartling\Models\RelatedContentInfo;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class Slides extends Unknown {
    public function getType(): string
    {
        return 'slides';
    }

    public function getTranslatableStrings(): array
    {
        $return = [];
        foreach ($this->settings['slides'] ?? [] as $slide) {
            foreach (['content', 'title'] as $field) {
                $return[$slide['_id']][$field] = $slide[$field];
            }
        }

        return [$this->getId() => $return];
    }

    public function setTargetContent(RelatedContentInfo $info, array $strings, SubmissionEntity $submission, SubmissionManager $submissionManager): static
    {
        foreach ($strings[$this->id] ?? [] as $array) {
            if (is_array($array)) {
                foreach ($array as $id => $values) {
                    foreach ($this->settings['slides'] ?? [] as $index => $slide) {
                        if (($slide['_id'] ?? '') === $id) {
                            foreach ($values as $property => $value) {
                                $this->settings['slides'][$index][$property] = $value;
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
