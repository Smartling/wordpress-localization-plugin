<?php

namespace Smartling\API;

use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Vendor\GuzzleHttp\RequestOptions;
use Smartling\Vendor\Smartling\AuthApi\AuthApiInterface;
use Smartling\Vendor\Smartling\File\FileApi;

class FileApiExtended extends FileApi
{
    use LoggerSafeTrait;

    private array $additionalHeaders;

    public function __construct(AuthApiInterface $auth, string $projectId, array $additionalHeaders = [])
    {
        parent::__construct($projectId, self::initializeHttpClient(self::ENDPOINT_URL), $this->getLogger(), self::ENDPOINT_URL);
        $this->setAuth($auth);
        $this->additionalHeaders = $additionalHeaders;
    }

    protected function getDefaultRequestData($parametersType, $parameters, $auth = true, $httpErrors = false): array
    {
        $data = parent::getDefaultRequestData($parametersType, $parameters, $auth, $httpErrors);
        if (!array_key_exists(RequestOptions::HEADERS, $data)) {
            $data[RequestOptions::HEADERS] = [];
        }
        $data[RequestOptions::HEADERS] = array_merge($data[RequestOptions::HEADERS], $this->additionalHeaders);
        return $data;
    }
}
