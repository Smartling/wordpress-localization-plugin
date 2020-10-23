<?php

namespace PHPSTORM_META {

    use Smartling\Helpers\ArrayHelper;
    use Smartling\Helpers\WordpressFunctionProxyHelper;

    override(ArrayHelper::first(0), elementType(0));
    override(WordpressFunctionProxyHelper::apply_filters(0), elementType(1));
    exitPoint(\Smartling\Services\BaseAjaxServiceAbstract::returnResponse());
    exitPoint(\Smartling\Helpers\WordpressFunctionProxyHelper::wp_send_json());
    exitPoint(\wp_send_json());
    exitPoint(\wp_send_json_error());
}
