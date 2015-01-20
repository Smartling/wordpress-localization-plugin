<?php

namespace Smartling\DbAl;

use Smartling\Helpers\SiteHelper;

interface MultilangPluginProxy {

    function __construct($logger, SiteHelper $helper, $ml_plugin_statuses);

    function getBlogLocaleById($blogId);

    function getLnkedBlogsByBlodId($blogId);
}