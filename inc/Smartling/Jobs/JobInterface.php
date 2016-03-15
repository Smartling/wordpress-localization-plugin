<?php

namespace Smartling\Jobs;

/**
 * Interface JobInterface
 * @package Smartling\Jobs
 */
interface JobInterface
{
    /**
     * @return void
     */
    public function install();

    /**
     * @return void
     */
    public function uninstall();

    /**
     * @return string
     */
    public function getJobRunInterval();

    /**
     * @return string
     */
    public function getJobHookName();

    /**
     * @return executes job
     */
    public function run();
}