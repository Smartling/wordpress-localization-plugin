<?php

namespace Smartling\Helpers;


class ReplacementPair
{
    private $from;
    private $to;

    /**
     * @param string $from
     * @param string $to
     */
    public function __construct($from, $to)
    {
        if (!is_string($from)) {
            throw new \InvalidArgumentException('From expected to be string');
        }
        if (!is_string($to)) {
            throw new \InvalidArgumentException('To expected to be string');
        }
        $this->from = $from;
        $this->to = $to;
    }

    /**
     * @return string
     */
    public function getFrom()
    {
        return $this->from;
    }

    /**
     * @return string
     */
    public function getTo()
    {
        return $this->to;
    }
}
