<?php

namespace Smartling\ContentTypes;

use Smartling\ContentTypes\Elementor\ElementFactory4;
use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Helpers\LinkProcessor;
use Smartling\Helpers\PluginHelper;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\UserHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Submissions\SubmissionManager;

class ExternalContentElementor4 extends ExternalContentElementorAbstract
{
    public function __construct(
        ContentTypeHelper $contentTypeHelper,
        ElementFactory4 $elementFactory,
        FieldsFilterHelper $fieldsFilterHelper,
        PluginHelper $pluginHelper,
        SiteHelper $siteHelper,
        SubmissionManager $submissionManager,
        UserHelper $userHelper,
        WordpressFunctionProxyHelper $wpProxy,
        LinkProcessor $linkProcessor,
    ) {
        parent::__construct(
            $contentTypeHelper,
            $elementFactory,
            $fieldsFilterHelper,
            $pluginHelper,
            $siteHelper,
            $submissionManager,
            $userHelper,
            $wpProxy,
            $linkProcessor,
        );
    }

    public function getMaxVersion(): string
    {
        return '4';
    }

    public function getMinVersion(): string
    {
        return '4';
    }
}
