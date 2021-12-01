<?php

namespace Smartling\Jobs;

interface JobInterface
{
    public function install(): void;

    public function uninstall(): void;

    public function getJobHookName(): string;

    public function run(): void;
}
