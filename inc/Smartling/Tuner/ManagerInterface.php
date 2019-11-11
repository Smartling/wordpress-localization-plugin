<?php

namespace Smartling\Tuner;

/**
 * Interface ManagerInterface
 * @package Smartling\Tuner
 */
interface ManagerInterface
{
    /**
     * @return array[]
     */
    public function listItems();

    /**
     * @param string $id
     * @param array  $data
     *
     * @return mixed
     */
    public function updateItem($id, array $data);

    /**
     * @param string $id
     *
     * @return mixed
     */
    public function removeItem($id);

    /**
     * @param string $id
     *
     * @return mixed
     */
    public function getItem($id);
}