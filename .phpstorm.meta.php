<?php

namespace PHPSTORM_META {

    use Smartling\Helpers\ArrayHelper;
    use Smartling\Helpers\WordpressFunctionProxyHelper;

    override(ArrayHelper::first(0), elementType(0));
    override(\Inpsyde\MultilingualPress\resolve(0), type(0));
    override(WordpressFunctionProxyHelper::apply_filters(0), type(1));
    //exitPoints don't seem to work without a fully qualified class
    exitPoint(\Smartling\Services\BaseAjaxServiceAbstract::returnError());
    exitPoint(\Smartling\Services\BaseAjaxServiceAbstract::returnResponse());
    exitPoint(\Smartling\Services\BaseAjaxServiceAbstract::returnSuccess());
    exitPoint(\Smartling\WP\Controller\ConfigurationProfilesController::processCnqAction());
    exitPoint(\Smartling\Helpers\WordpressFunctionProxyHelper::wp_send_json());
    exitPoint(\wp_send_json());
    exitPoint(\wp_send_json_error());
}
