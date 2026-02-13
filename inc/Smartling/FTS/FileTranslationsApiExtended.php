<?php

namespace Smartling\FTS;

use Smartling\Vendor\GuzzleHttp\RequestOptions;
use Smartling\Vendor\Smartling\AuthApi\AuthApiInterface;
use Smartling\Vendor\Smartling\FileTranslations\FileTranslationsApi;

class FileTranslationsApiExtended extends FileTranslationsApi
{
    private const SERVICE_ORIGIN_HEADER = 'X-SL-ServiceOrigin';
    private const SERVICE_ORIGIN_VALUE = 'wordpress';

    private array $additionalHeaders = [];

    public function __construct($accountUid, $client, $logger = null, $service_url = null)
    {
        parent::__construct($accountUid, $client, $logger, $service_url);

        $this->additionalHeaders[self::SERVICE_ORIGIN_HEADER] = self::SERVICE_ORIGIN_VALUE;
    }

    /**
     * @param string $accountUid
     */
    public static function create(
        AuthApiInterface $authProvider,
        $accountUid,
        $logger = null,
    ): FileTranslationsApiExtended {
        $client = self::initializeHttpClient(self::ENDPOINT_URL);

        $instance = new self($accountUid, $client, $logger, self::ENDPOINT_URL);
        $instance->setAuth($authProvider);

        return $instance;
    }

    protected function getDefaultRequestData($parametersType, $parameters, $auth = true, $httpErrors = false): array
    {
        $data = parent::getDefaultRequestData($parametersType, $parameters, $auth, $httpErrors);

        if (!isset($data[RequestOptions::HEADERS])) {
            $data[RequestOptions::HEADERS] = [];
        }

        $data[RequestOptions::HEADERS] = array_merge(
            $data[RequestOptions::HEADERS],
            $this->additionalHeaders
        );

        return $data;
    }
}
