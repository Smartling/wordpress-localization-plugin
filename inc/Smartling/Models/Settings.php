<?php

namespace Smartling\Models;

class Settings {

    public function __construct(
        private string $userIdentifier,
        private string $secretKey,
        private string $retrievalType,
        private string $sourceLocale,
        private int $sourceBlogId,
        private array $targetLocales,
    ) {
    }

    public static function fromArray(array $array): self
    {
        return new self(
            $array['userIdentifier'],
            $array['secretKey'],
            $array['retrievalType'],
            $array['sourceLocale'],
            $array['sourceBlogId'],
            $array['targetLocales'],
        );
    }

    public function getUserIdentifier(): string
    {
        return $this->userIdentifier;
    }

    public function getSecretKey(): string
    {
        return $this->secretKey;
    }

    public function getRetrievalType(): string
    {
        return $this->retrievalType;
    }

    public function getSourceBlogId(): int
    {
        return $this->sourceBlogId;
    }

    public function getSourceLocale(): string
    {
        return $this->sourceLocale;
    }

    public function getTargetLocales(): array
    {
        return $this->targetLocales;
    }
}
