<?php

namespace Smartling\Models;

use Smartling\Exception\SmartlingHumanReadableException;
use Smartling\Helpers\ArrayHelper;

class CloneRequest
{
    private int $contentId;
    private string $contentType;
    private array $relations;
    private array $targetBlogIds;

    public function __construct(int $contentId, string $contentType, array $relations, array $targetBlogIds)
    {
        $this->contentId = $contentId;
        $this->contentType = $contentType;
        krsort($relations);
        $this->relations = $relations;
        $this->targetBlogIds = ArrayHelper::toArrayOfIntegers($targetBlogIds, 'Target blog id expected to be numeric');
    }

    public function getContentId(): int
    {
        return $this->contentId;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function getRelationsOrdered(): array
    {
        return $this->relations;
    }

    /**
     * @return int[]
     */
    public function getTargetBlogIds(): array
    {
        return $this->targetBlogIds;
    }

    public static function fromArray(array $array): self
    {
        return new self(self::getSourceId($array), $array['source']['contentType'], $array['relations'] ?? [], explode(',', $array['targetBlogIds']));
    }

    // Might be 0 in case of bulk upload
    protected static function getSourceId(array $array): int
    {
        $id = $array['source']['id'][0] ?? null;
        if ($id === null) {
            throw new SmartlingHumanReadableException('Source content id is empty, please save content prior to uploading', 'source.id.empty', 400);
        }
        return (int)$id;
    }
}
