<?php

namespace Smartling\DbAl;
use Smartling\Exception\SmartlingDbException;
use Smartling\Exception\SmartlingDataUpdateException;

/**
 * Interface EntityAccessorInterface
 * @package Smartling\DbAl
 */
interface EntityAccessorInterface {

    /**
     * Reads Entity from database with given $id and $locale
     * @param integer $id
     * @param sting $locale
     * @return array
     * @throws SmartlingDbException
     */
    function read($id, $locale);

    /**
     * @param integer $id
     * @param string $locale
     * @param array $data
     * @return void
     * @throws SmartlingDataUpdateException
     */
    function write($id, $locale, $data);
}