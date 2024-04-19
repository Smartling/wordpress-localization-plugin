<?php

namespace Smartling\ContentTypes\Elementor\Elements;

use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\ContentTypes\ExternalContentElementor;
use Smartling\Models\Content;
use Smartling\Models\RelatedContentInfo;
use Smartling\Submissions\Submission;

class Reviews extends Unknown {
    public function getType(): string
    {
        return 'reviews';
    }

    public function getRelated(): RelatedContentInfo
    {
        $return = parent::getRelated();
        $key = 'image/id';

        $id = $this->getIntSettingByKey($key, $this->settings);
        if ($id !== null) {
            $return->addContent(new Content($id, ContentTypeHelper::POST_TYPE_ATTACHMENT), $this->id, "settings/$key");
        }

        return $return;
    }

    public function getTranslatableStrings(): array
    {
        $return = [];
        foreach ($this->settings['slides'] ?? [] as $slide) {
            foreach (['content', 'name', 'title'] as $field) {
                $return[$slide['_id']][$field] = $slide[$field];
            }
        }

        return [$this->getId() => $return];
    }

    public function setTargetContent(
        ExternalContentElementor $externalContentElementor,
        RelatedContentInfo $info,
        array $strings,
        Submission $submission,
    ): static {
        foreach ($strings[$this->id] ?? [] as $key => $array) {
            if (is_array($array)) {
                foreach ($array as $property => $translation) {
                    foreach ($this->settings['slides'] ?? [] as $index => $slide) {
                        if (($slide['_id'] ?? '') === $key) {
                            $this->settings['slides'][$index][$property] = $translation;
                        }
                    }
                }
            }
        }
        $this->raw['settings'] = $this->settings;

        return new static($this->raw);
    }
}
