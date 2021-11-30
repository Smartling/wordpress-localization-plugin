<?php

namespace Smartling\API;

use Smartling\Vendor\GuzzleHttp\Client;
use Smartling\Vendor\GuzzleHttp\RequestOptions;

class ClientExtended extends Client
{
    public function withAdditionalHeaders(array $additionalHeaders): Client
    {
        $config = $this->getConfig();
        if (!array_key_exists(RequestOptions::HEADERS, $config)) {
            $config[RequestOptions::HEADERS] = [];
        }
        $config[RequestOptions::HEADERS] = array_merge($config[RequestOptions::HEADERS], $additionalHeaders);

        return new Client($config);
    }
}
