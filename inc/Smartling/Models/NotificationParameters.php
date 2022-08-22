<?php

namespace Smartling\Models;

class NotificationParameters {
    private string $contentId;
    private string $message;
    private string $projectId;
    private string $severity;

    public function __construct(string $contentId, string $message, string $projectId, string $severity)
    {
        $this->contentId = $contentId;
        $this->message = $message;
        $this->projectId = $projectId;
        $this->severity = $severity;
    }

    public function getContentId(): string
    {
        return $this->contentId;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getProjectId(): string
    {
        return $this->projectId;
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }
}
