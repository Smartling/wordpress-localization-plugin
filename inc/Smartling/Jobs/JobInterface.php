<?php

namespace Smartling\Jobs;

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
     * @return void
     */
    public function run();
}