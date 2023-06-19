<?php

namespace Smartling\Jobs;

class JobEntityWithStatus
{
    private string $status;
    private JobEntity $jobInfo;

    public function __construct(string $status, string $jobName, string $jobUid, string $projectUid, ?int $id = null, ?\DateTime $created = null, ?\DateTime $modified = null)
    {
        $this->jobInfo = new JobEntity($jobName, $jobUid, $projectUid, $id, $created, $modified);
        $this->status = $status;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getJobInformationEntity(): JobEntity
    {
        return $this->jobInfo;
    }
}
