<?php

namespace Smartling\API;

use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Vendor\Smartling\AuthApi\AuthApiInterface;
use Smartling\Vendor\Smartling\File\FileApi;

class FileApiExtended extends FileApi
{
    use LoggerSafeTrait;

    public function __construct(AuthApiInterface $auth, string $projectId, array $additionalHeaders = [])
    {
        $client = self::initializeHttpClient(self::ENDPOINT_URL);
        $client = (new ClientExtended($client->getConfig()))->withAdditionalHeaders($additionalHeaders);
        parent::__construct($projectId, $client, $this->getLogger(), self::ENDPOINT_URL);
        $this->setAuth($auth);
    }
}
