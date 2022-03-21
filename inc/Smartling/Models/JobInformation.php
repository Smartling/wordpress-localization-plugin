<?php

namespace Smartling\Models;

class JobInformation
{
    private bool $authorize;
    private string $description;
    private string $dueDate;
    private string $id;
    private string $name;
    private string $timeZone;

    public function __construct(string $id, bool $authorize, string $name, string $description, string $dueDate, string $timeZone)
    {
        $this->id = $id;
        $this->authorize = $authorize;
        $this->name = $name;
        $this->description = $description;
        $this->dueDate = $dueDate;
        $this->timeZone = $timeZone;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function isAuthorize(): bool
    {
        return $this->authorize;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getDueDate(): string
    {
        return $this->dueDate;
    }

    public function getTimeZone(): string
    {
        return $this->timeZone;
    }
}
