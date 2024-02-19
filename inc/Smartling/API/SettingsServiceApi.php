<?php

namespace Smartling\API;

use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Vendor\Smartling\AuthApi\AuthApiInterface;
use Smartling\Vendor\Smartling\BaseApiAbstract;

class SettingsServiceApi extends BaseApiAbstract
{
    use LoggerSafeTrait;

    private const ENDPOINT_URL = 'https://api.smartling.com/connectors-settings-api/v2/projects';

    public function __construct(AuthApiInterface $auth, string $projectId)
    {
        parent::__construct($projectId, self::initializeHttpClient(self::ENDPOINT_URL), $this->getLogger(), self::ENDPOINT_URL);
        $this->setAuth($auth);
    }

    public function getSettings(): array
    {
        return $this->sendRequest('/integrations/wordpress/settings', $this->getDefaultRequestData('query', []), self::HTTP_METHOD_GET);
    }
}
