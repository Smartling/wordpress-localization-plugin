<?php

namespace Smartling\Helpers;

class ReplacementInfo
{
    private string $result;
    private array $replacementPairs;

    /**
     * @param ReplacementPair[] $replacementPairs
     */
    public function __construct(string $resultString, array $replacementPairs)
    {
        foreach ($replacementPairs as $replacementPair) {
            if (!$replacementPair instanceof ReplacementPair) {
                throw new \InvalidArgumentException('ReplacementPairs expected');
            }
        }
        $this->replacementPairs = $replacementPairs;
        $this->result = $resultString;
    }

    public function getResult(): string
    {
        return $this->result;
    }

    /**
     * @return ReplacementPair[]
     */
    public function getReplacementPairs(): array
    {
        return $this->replacementPairs;
    }
}
