<?php

namespace Smartling\Helpers;

class PairReplacerHelper
{
    /**
     * @var ReplacementPair[] $pairCollection
     */
    private $pairCollection = [];

    public function addReplacementPair(ReplacementPair $replacementPair)
    {
        $this->pairCollection[] = $replacementPair;
    }

    /**
     * @param string $string
     * @param string[] $pairWrapper
     *
     * @return string
     */
    public function processString($string, $pairWrapper = ['\'', '"', '\\"'])
    {
        $this->removeDuplicates();
        foreach ($this->pairCollection as $pair) {
            $pattern = '%s%s%s';
            foreach ($pairWrapper as $wrapper) {
                $search = vsprintf($pattern, [$wrapper, $pair->getFrom(), $wrapper]);
                $replace = vsprintf($pattern, [$wrapper, $pair->getTo(), $wrapper]);

                $string = str_replace($search, $replace, $string);
            }
        }

        return $string;
    }

    private function removeDuplicates() {
        /**
         * @var ReplacementPair[] $result
         */
        $result = [];
        foreach ($this->pairCollection as $replacementPair) {
            foreach ($result as $item) {
                if ($item->getFrom() === $replacementPair->getFrom() && $item->getTo() === $replacementPair->getTo()) {
                    break 2;
                }
            }
            $result[] = $replacementPair;
        }

        $this->pairCollection = $result;
    }
}
