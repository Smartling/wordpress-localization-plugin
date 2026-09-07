<?php

namespace Smartling\Models;

use Smartling\Exception\SmartlingHumanReadableException;
use Smartling\Helpers\ArrayHelper;

class UserTranslationRequest
{
    private int $contentId;
    private string $contentType;
    private string $description;
    private array $relations;
    private array $targetBlogIds;
    private JobInformation $jobInformation;
    private array $ids;

    public function __construct(int $contentId, string $contentType, array $relations, array $targetBlogIds, JobInformation $jobInformation, array $ids = [], string $description = '')
    {
        $this->contentId = $contentId;
        $this->contentType = $contentType;
        $this->description = $description;
        krsort($relations);
        $this->relations = $relations;
        $this->targetBlogIds = ArrayHelper::toArrayOfIntegers($targetBlogIds, 'Target blog id expected to be numeric');
        $this->jobInformation = $jobInformation;
        $this->ids = self::toIntegerArray($ids);
    }

    public function getContentId(): int
    {
        return $this->contentId;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function getDescription(): string
    {
        return $this->description;
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

    public function getJobInformation(): JobInformation
    {
        return $this->jobInformation;
    }

    public function getIds(): array
    {
        return $this->ids;
    }

    public static function fromArray(array $array): self
    {
        self::validate($array);
        $ids = self::toIntegerArray($array['ids'] ?? []);
        $contentId = count($ids) > 0 ? 0 : self::getSourceId($array);

        return new self(
            $contentId,
            $array['source']['contentType'] ?? '',
            $array['relations'] ?? [],
            explode(',', $array['targetBlogIds']),
            new JobInformation($array['job']['id'], $array['job']['authorize'] === 'true', $array['job']['name'], $array['job']['description'], $array['job']['dueDate'], $array['job']['timeZone']),
            $ids,
            $array['description'] ?? (count($ids) > 0 ? 'From Bulk Submit' : 'From Widget'),
        );
    }

    public function isBulk(): bool
    {
        return count($this->ids) > 0;
    }

    private static function getSourceId(array $array): int
    {
        $id = $array['source']['id'][0] ?? null;
        if ($id === null) {
            throw new SmartlingHumanReadableException('Source content id is empty, please save content prior to uploading', 'source.id.empty', 400);
        }
        return (int)$id;
    }

    private static function validate(array $array): void
    {
        if (!array_key_exists('source', $array)) {
            throw new \InvalidArgumentException('Source array required');
        }
        if (!array_key_exists('job', $array)) {
            throw new \InvalidArgumentException('Job array required');
        }
        if (!array_key_exists('id', $array['job'])) {
            throw new \InvalidArgumentException('Job id required');
        }
        if (!array_key_exists('authorize', $array['job'])) {
            throw new \InvalidArgumentException('Job authorize key required');
        }
        if (!array_key_exists('name', $array['job'])) {
            throw new \InvalidArgumentException('Job name required');
        }
        if (!array_key_exists('description', $array['job'])) {
            throw new \InvalidArgumentException('Job description required');
        }
        if (!array_key_exists('dueDate', $array['job'])) {
            throw new \InvalidArgumentException('Job due date required');
        }
        if (!array_key_exists('timeZone', $array['job'])) {
            throw new \InvalidArgumentException('Job time zone required');
        }
    }

    private static function toIntegerArray(array $ids): array
    {
        return ArrayHelper::toArrayOfIntegers($ids, 'Content id expected to be numeric');
    }
}
