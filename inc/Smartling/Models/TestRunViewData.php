<?php

namespace Smartling\Models;

class TestRunViewData {
    private array $blogs;
    private int $completed;
    private int $failed;
    private int $inProgress;
    private int $new;
    private ?int $testBlogId;
    private int $uploadCronLastFinishTime;
    private int $uploadCronIntervalSeconds;

    public function __construct(
        array $blogs,
        ?int $testBlogId,
        int $new,
        int $inProgress,
        int $completed,
        int $failed,
        int $uploadCronLastFinishTime,
        int $uploadCronIntervalSeconds
    )
    {
        $this->blogs = $blogs;
        $this->completed = $completed;
        $this->failed = $failed;
        $this->inProgress = $inProgress;
        $this->new = $new;
        $this->testBlogId = $testBlogId;
        $this->uploadCronLastFinishTime = $uploadCronLastFinishTime;
        $this->uploadCronIntervalSeconds = $uploadCronIntervalSeconds;
    }

    public function getBlogs(): array
    {
        return $this->blogs;
    }

    public function getCompleted(): int
    {
        return $this->completed;
    }

    public function getFailed(): int
    {
        return $this->failed;
    }

    public function getInProgress(): int
    {
        return $this->inProgress;
    }

    public function getNew(): int
    {
        return $this->new;
    }

    public function getTestBlogId(): ?int
    {
        return $this->testBlogId;
    }

    public function getUploadCronLastFinishTime(): int
    {
        return $this->uploadCronLastFinishTime;
    }

    public function getUploadCronIntervalSeconds(): int
    {
        return $this->uploadCronIntervalSeconds;
    }
}
