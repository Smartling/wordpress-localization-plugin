<?php

namespace Smartling\ContentTypes\Elementor\Elements;

use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\ContentTypes\ExternalContentElementor;
use Smartling\Models\Content;
use Smartling\Models\RelatedContentInfo;
use Smartling\Submissions\SubmissionEntity;

class Gallery extends Unknown {
    public function getType(): string
    {
        return 'gallery';
    }

    public function getRelated(): RelatedContentInfo
    {
        $return = parent::getRelated();
        foreach ($this->settings['gallery'] ?? [] as $index => $listItem) {
            $key = "gallery/$index/id";
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
        foreach ($this->settings['galleries'] ?? [] as $gallery) {
            $id = $gallery['_id'] ?? null;
            if ($id !== null && array_key_exists('gallery_title', $gallery)) {
                $return['galleries/' . $id]['gallery_title'] = $gallery['gallery_title'];
            }
        }

        return [$this->getId() => $return];
    }

    public function setTargetContent(ExternalContentElementor $externalContentElementor, RelatedContentInfo $info, array $strings, SubmissionEntity $submission): static
    {
        foreach ($strings[$this->id] ?? [] as $array) {
            if (is_array($array)) {
                foreach ($array as $id => $values) {
                    foreach ($this->settings['galleries'] ?? [] as $index => $gallery) {
                        if (($gallery['_id'] ?? '') === $id) {
                            foreach ($values as $property => $value) {
                                $this->settings['galleries'][$index][$property] = $value;
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
