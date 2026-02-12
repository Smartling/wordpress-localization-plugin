<?php

namespace Smartling\FTS;

use Psr\Log\LoggerInterface;
use Smartling\Vendor\GuzzleHttp\RequestOptions;
use Smartling\Vendor\Smartling\AuthApi\AuthApiInterface;
use Smartling\Vendor\Smartling\FileTranslations\FileTranslationsApi;

/**
 * Extended FileTranslations API with custom header support
 *
 * Extends the base FileTranslationsApi to inject the X-SL-ServiceOrigin header
 * required for FTS (Fast Translation Service) requests.
 */
class FileTranslationsApiExtended extends FileTranslationsApi
{
    private const SERVICE_ORIGIN_HEADER = 'X-SL-ServiceOrigin';
    private const SERVICE_ORIGIN_VALUE = 'wordpress';

    /**
     * Additional headers to inject into all requests
     *
     * @var array
     */
    private array $additionalHeaders = [];

    /**
     * {@inheritdoc}
     */
    public function __construct($accountUid, $client, $logger = null, $service_url = null)
    {
        parent::__construct($accountUid, $client, $logger, $service_url);

        // Set the required service origin header
        $this->additionalHeaders[self::SERVICE_ORIGIN_HEADER] = self::SERVICE_ORIGIN_VALUE;
    }

    /**
     * Factory method to create FileTranslationsApiExtended instance.
     *
     * @param AuthApiInterface $authProvider
     *   Authentication provider
     * @param string $accountUid
     *   Account UID in Smartling dashboard
     * @param LoggerInterface|null $logger
     *   Logger instance
     *
     * @return FileTranslationsApiExtended
     */
    public static function create($authProvider, $accountUid, $logger = null)
    {
        $client = self::initializeHttpClient(self::ENDPOINT_URL);

        $instance = new self($accountUid, $client, $logger, self::ENDPOINT_URL);
        $instance->setAuth($authProvider);

        return $instance;
    }

    /**
     * {@inheritdoc}
     *
     * Overrides to inject additional headers into all requests
     */
    protected function getDefaultRequestData($parametersType, $parameters, $auth = true, $httpErrors = false): array
    {
        $data = parent::getDefaultRequestData($parametersType, $parameters, $auth, $httpErrors);

        // Merge additional headers into request
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
