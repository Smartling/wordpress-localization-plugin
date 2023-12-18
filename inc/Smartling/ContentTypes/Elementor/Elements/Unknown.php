<?php

namespace Smartling\ContentTypes\Elementor\Elements;

use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\ContentTypes\Elementor\Element;
use Smartling\ContentTypes\Elementor\ElementAbstract;
use Smartling\ContentTypes\Elementor\ElementFactory;
use Smartling\Helpers\ArrayHelper;
use Smartling\Models\Content;
use Smartling\Models\RelatedContentInfo;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class Unknown extends ElementAbstract {
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
}
