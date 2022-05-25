<?php


namespace Smartling\Services;

use Smartling\WP\WPHookInterface;

abstract class BaseAjaxServiceAbstract implements WPHookInterface
{
    /**
     * Action name
     */
    const ACTION_NAME = 'service-id';

    public const RESPONSE_FAILED = 'FAILED';
    public const RESPONSE_SUCCESS = 'SUCCESS';

    protected array $requestData;

    public function __construct(array $requestData)
    {
        $this->requestData = $requestData;
    }

    /**
     * Registers wp hook handlers. Invoked by wordpress.
     *
     * @return void
     */
    public function register(): void
    {
        add_action('wp_ajax_' . static::ACTION_NAME, [$this, 'actionHandler']);
    }

    /**
     * @param mixed $defaultValue
     * @return mixed
     */
    public function getRequestVariable(string $varName, $defaultValue = null)
    {
        $vars = $this->requestData;

        return array_key_exists($varName, $vars) ? $vars[$varName] : $defaultValue;
    }

    public function returnResponse(array $data, $responseCode = 200): void
    {
        wp_send_json($data, $responseCode);
    }

    public function returnError($key, $message, $responseCode = 400): void
    {
        $this->returnResponse(
            [
                'status' => self::RESPONSE_FAILED,
                'response' => [
                    'key'     => $key,
                    'message' => $message,
                ],
            ],
            $responseCode
        );
    }

    public function getRequiredParam(string $paramName): string
    {
        $value = $this->getRequestVariable($paramName);

        if (is_null($value)) {
            $this->returnError(vsprintf('key.%s.required', [$paramName]), vsprintf('\'%s\' is required', [$paramName]));
        }

        return $value;
    }

    public function returnSuccess($data, $responseCode = 200): void
    {
        $this->returnResponse(
            [
                'status' => self::RESPONSE_SUCCESS,
                'response' => $data,
            ],
            $responseCode
        );
    }
}
