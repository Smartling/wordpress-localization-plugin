<?php

namespace PHPSTORM_META {

    use Smartling\Helpers\ArrayHelper;

    override(ArrayHelper::first(0), elementType(0));
    exitPoint(\Smartling\Services\BaseAjaxServiceAbstract::returnResponse());
    exitPoint(\Smartling\Helpers\WordpressFunctionProxyHelper::wp_send_json());
    exitPoint(\wp_send_json());
    exitPoint(\wp_send_json_error());
}
