<?php

namespace Smartling\Jobs;

class JobInformationEntityWithBatchUid extends JobInformationEntity
{
    private string $batchUid;
    private parent $jobInfo;

    public function __construct(string $batchUid, string $jobName, string $jobUid, string $projectUid, ?int $id = null, ?\DateTime $created = null, ?\DateTime $modified = null)
    {
        parent::__construct($jobName, $jobUid, $projectUid, $id, $created, $modified);
        $this->batchUid = $batchUid;
        $this->jobInfo = new parent($jobName, $jobUid, $projectUid, $id, $created, $modified);
    }

    public function getBatchUid(): string
    {
        return $this->batchUid;
    }

    public function getJobInformationEntity(): parent
    {
        return $this->jobInfo;
    }
}
