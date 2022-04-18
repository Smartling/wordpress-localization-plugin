<?php

namespace Smartling\Models;

use Smartling\Helpers\ArrayHelper;

class TranslationRequest extends CloneRequest
{
    private JobInformation $jobInformation;
    private array $ids;

    public function __construct(int $contentId, string $contentType, array $relations, array $targetBlogIds, JobInformation $jobInformation, array $ids = [], string $description = '')
    {
        parent::__construct($contentId, $contentType, $relations, $targetBlogIds, $description);
        $this->jobInformation = $jobInformation;
        $this->ids = self::toIntegerArray($ids);
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
        return new self(
            self::getSourceId($array),
            $array['source']['contentType'] ?? '',
            $array['relations'] ?? [],
            explode(',', $array['targetBlogIds']),
            new JobInformation($array['job']['id'], $array['job']['authorize'] === 'true', $array['job']['name'], $array['job']['description'], $array['job']['dueDate'], $array['job']['timeZone']),
            self::toIntegerArray($array['ids'] ?? []),
            $array['description'] ?? '',
        );
    }

    public function isBulk(): bool
    {
        return count($this->ids) > 0;
    }

    private static function validate(array $array)
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
