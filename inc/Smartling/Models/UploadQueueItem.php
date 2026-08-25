<?php

namespace Smartling\Models;

use JetBrains\PhpStorm\Pure;
use Smartling\Submissions\SubmissionEntity;

class UploadQueueItem {
    /**
     * @param SubmissionEntity[] $submissions
     */
    public function __construct(
        private array $submissions,
        private string $batchUid,
        private IntStringPairCollection $smartlingLocales,
        private ?int $id = null,
    ) {
        $contentTypes = [];
        $sourceBlogIds = [];
        $sourceIds = [];

        foreach ($this->submissions as $submission) {
            if (!$submission instanceof SubmissionEntity) {
                throw new \InvalidArgumentException("Submissions expected to be array of " . SubmissionEntity::class);
            }
            $contentTypes[] = $submission->getContentType();
            $sourceBlogIds[] = $submission->getSourceBlogId();
            $sourceIds[] = $submission->getSourceId();
        }
        if (count(array_unique($contentTypes)) > 1) {
            throw new \InvalidArgumentException('Submissions expected to reference same content type');
        }
        if (count(array_unique($sourceBlogIds)) > 1) {
            throw new \InvalidArgumentException('Submissions expected to reference same source blog id');
        }
        if (count(array_unique($sourceIds)) > 1) {
            throw new \InvalidArgumentException('Submissions expected to reference same source id');
        }
        if (count($submissions) !== count($smartlingLocales->getArray())) {
            throw new \InvalidArgumentException("Count of submissions expected to be equal to count of SmartlingLocales");
        }
    }

    public function getBatchUid(): string
    {
        return $this->batchUid;
    }

    /**
     * Identifies the originating upload queue row, so it can be removed once the
     * upload succeeds. Null when the item was not read from the queue.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSmartlingLocales(): IntStringPairCollection
    {
        return $this->smartlingLocales;
    }

    /**
     * @return SubmissionEntity[];
     */
    public function getSubmissions(): array
    {
        return $this->submissions;
    }

    public function removeSubmission(SubmissionEntity $submission): self
    {
        $submissions = array_values(array_filter($this->submissions, static function (SubmissionEntity $item) use ($submission) {
            return $item->getId() !== $submission->getId();
        }));
        $locales = new IntStringPairCollection(array_values(array_filter($this->smartlingLocales->getArray(), static function (IntStringPair $item) use ($submission) {
            return $item->getKey() !== $submission->getId();
        })));

        return new self($submissions, $this->batchUid, $locales, $this->id);
    }

    #[Pure]
    public function setBatchUid(string $batchUid): self
    {
        $result = clone $this;
        $result->batchUid = $batchUid;

        return $result;
    }
}
