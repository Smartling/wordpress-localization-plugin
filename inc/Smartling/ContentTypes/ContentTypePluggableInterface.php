<?php

namespace Smartling\ContentTypes;

use Smartling\Submissions\SubmissionEntity;

interface ContentTypePluggableInterface
{
    public function getContentFields(SubmissionEntity $submission): array;

    public function getMaxVersion(): string;

    public function getMinVersion(): string;

    public function getPluginPath(): string;

    public function getPluginSlug(): string;

    public function setContentFields(array $content, SubmissionEntity $submission): void;
}
