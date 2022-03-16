<?php

namespace Smartling\Models;

use Smartling\Helpers\ArrayHelper;

class TranslationRequest extends CloneRequest
{
    private JobInformation $jobInformation;
    private array $ids;

    public function __construct(int $contentId, string $contentType, array $relations, array $targetBlogIds, JobInformation $jobInformation, array $ids)
    {
        parent::__construct($contentId, $contentType, $relations, $targetBlogIds);
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
        return new self(
            self::getSourceId($array),
            $array['source']['contentType'],
            $array['relations'] ?? [],
            explode(',', $array['targetBlogIds']),
            new JobInformation($array['job']['id'], $array['job']['authorize'] === 'true', $array['job']['name'], $array['job']['description'], $array['job']['dueDate'], $array['job']['timeZone']),
            self::toIntegerArray($array['ids'] ?? []),
        );
    }

    public function isBulk(): bool
    {
        return count($this->ids) > 0;
    }

    private static function toIntegerArray(array $ids): array
    {
        return ArrayHelper::toArrayOfIntegers($ids, 'Content id expected to be numeric');
    }
}
