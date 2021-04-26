<?php

namespace Smartling\Jobs;

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
}
