<?php

namespace Smartling\Models;

use Smartling\Helpers\ArrayHelper;

class CloneRequest
{
    private int $contentId;
    private string $contentType;
    private array $relations;
    private array $targetBlogIds;

    /**
     * @param int $contentId
     * @param string $contentType
     * @param array{blogId: int} $relations
     * @param array $targetBlogIds
     */
    public function __construct(int $contentId, string $contentType, array $relations, array $targetBlogIds)
    {
        $this->contentId = $contentId;
        $this->contentType = $contentType;
        ksort($relations);
        $this->relations = $relations;
        $this->targetBlogIds = array_map(static function($targetBlogId) {
            if (!is_numeric($targetBlogId)) {
                throw new \InvalidArgumentException('Target blog id expected to be numeric');
            }
            return (int)$targetBlogId;
        }, $targetBlogIds);
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
}
