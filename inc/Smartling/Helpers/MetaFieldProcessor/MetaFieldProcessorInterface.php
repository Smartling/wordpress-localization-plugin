<?php

namespace Smartling\Helpers\MetaFieldProcessor;

/**
 * Interface MetaFieldProcessorInterface
 * @package Smartling\Helpers\MetaFieldProcessor
 */
interface MetaFieldProcessorInterface
{
    /**
     * @return string
     */
    public function getFieldName();

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    public function processFieldValue($value);
}