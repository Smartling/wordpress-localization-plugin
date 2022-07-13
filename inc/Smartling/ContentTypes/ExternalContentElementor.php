<?php

namespace Smartling\ContentTypes;

use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Helpers\PlaceholderHelper;
use Smartling\Helpers\QueryBuilder\Condition\Condition;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBlock;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBuilder;
use Smartling\Helpers\QueryBuilder\QueryBuilder;
use Smartling\Helpers\SiteHelper;
use Smartling\Submissions\SubmissionEntity;

class ExternalContentElementor implements ContentTypeModifyingInterface
{
    public function getContentFields(SubmissionEntity $submission, bool $raw): array
    {
        return [];
    }

    public function getMaxVersion(): string
    {
        return '3.4';
    }

    public function getMinVersion(): string
    {
        return '3.4';
    }

    public function getPluginId(): string
    {
        return 'elementor';
    }

    public function getPluginPath(): string
    {
        return 'elementor/elementor.php';
    }

    public function setContentFields(array $content, SubmissionEntity $submission): void
    {
    }

    public function alterContentFields(array $source): array
    {
        unset($source['post_content']);
        return $source;
    }
}
