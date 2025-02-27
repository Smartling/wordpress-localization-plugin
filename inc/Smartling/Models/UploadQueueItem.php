<?php

namespace Smartling\Models;

use Smartling\Submissions\SubmissionEntity;

readonly class UploadQueueItem
{
    /**
     * @param SubmissionEntity[] $submissions
     */
    public function __construct(
        public array $submissions,
        public string $batchUid,
        public IntStringPairCollection $smartlingLocales,
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

    public function removeSubmission(SubmissionEntity $submission): self
    {
        $submissions = array_values(array_filter($this->submissions, static function (SubmissionEntity $item) use ($submission) {
            return $item->getId() !== $submission->getId();
        }));
        $locales = new IntStringPairCollection(array_values(array_filter($this->smartlingLocales->getArray(), static function (IntStringPair $item) use ($submission) {
            return $item->key !== $submission->getId();
        })));

        return new self($submissions, $this->batchUid, $locales);
    }

    public function setBatchUid(string $batchUid): self
    {
        return new self($this->submissions, $batchUid, $this->smartlingLocales);
    }
}
