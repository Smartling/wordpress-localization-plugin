<?php

namespace Smartling\Jobs;

use Smartling\Models\JobInformation;

class JobEntityWithBatchUid
{
    private string $batchUid;
    private JobEntity $jobInfo;

    public function __construct(string $batchUid, string $jobName, string $jobUid, string $projectUid, ?int $id = null, ?\DateTime $created = null, ?\DateTime $modified = null)
    {
        $this->jobInfo = new JobEntity($jobName, $jobUid, $projectUid, $id, $created, $modified);
        $this->batchUid = $batchUid;
    }

    public function getBatchUid(): string
    {
        return $this->batchUid;
    }

    public function getJobInformationEntity(): JobEntity
    {
        return $this->jobInfo;
    }

    public static function fromJob(JobEntity $jobInfo, string $batchUid): self
    {
        $result = new self(
            $batchUid,
            $jobInfo->getJobName(),
            $jobInfo->getJobUid(),
            $jobInfo->getProjectUid(),
            $jobInfo->getId(),
            $jobInfo->getCreated(),
            $jobInfo->getModified(),
        );

        return $result;
    }
}
