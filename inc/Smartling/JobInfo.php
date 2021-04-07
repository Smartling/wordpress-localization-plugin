<?php

namespace Smartling;

class JobInfo
{
    private $batchUid;
    private $jobName;

    public function __construct(string $batchUid, string $jobName)
    {
        $this->batchUid = $batchUid;
        $this->jobName = $jobName;
    }

    public function getBatchUid(): string
    {
        return $this->batchUid;
    }

    public function getJobName(): string
    {
        return $this->jobName;
    }
}
