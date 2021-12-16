<?php

namespace Smartling\Exception;

class SmartlingHumanReadableException extends SmartlingExceptionAbstract
{
    private string $key;
    private int $responseCode;
    public function __construct(string $message, string $key, int $responseCode)
    {
        parent::__construct($message);
        $this->key = $key;
        $this->responseCode = $responseCode;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getResponseCode(): int
    {
        return $this->responseCode;
    }
}
