<?php


namespace Smartling\Services;

use Smartling\WP\WPHookInterface;

abstract class BaseAjaxServiceAbstract implements WPHookInterface
{

    /**
     * Action name
     */
    const ACTION_NAME = 'service-id';

    /**
     * Registers wp hook handlers. Invoked by wordpress.
     *
     * @return void
     */
    public function register()
    {
        add_action('wp_ajax_' . static::ACTION_NAME, [$this, 'actionHandler']);
    }

    /**
     * @return array
     */
    public function getRequestSource()
    {
        return $_REQUEST;
    }

    /**
     * @param string $varName
     * @param bool   $defaultValue
     * @return mixed
     */
    public function getRequestVariable($varName, $defaultValue = false)
    {
        $vars = $this->getRequestSource();

        return array_key_exists($varName, $vars) ? $vars[$varName] : $defaultValue;
    }

    public function returnResponse(array $data, $responseCode = 200)
    {
        wp_send_json($data, $responseCode);
    }

    public function returnError($key, $message, $responseCode = 400)
    {
        $this->returnResponse(
            [
                'status'   => 'FAILED',
                'response' => [
                    'key'     => $key,
                    'message' => $message,
                ],
            ],
            $responseCode
        );
    }

    public function getRequiredParam($paramName)
    {
        $value = $this->getRequestVariable($paramName, null);

        if (is_null($value)) {
            $this->returnError(vsprintf('key.%s.required', [$paramName]), vsprintf('\'%s\' is required', [$paramName]));
        }

        return $value;
    }

    public function returnSuccess($data, $responseCode = 200)
    {
        $this->returnResponse(
            [
                'status'   => 'SUCCESS',
                'response' => $data,
            ],
            $responseCode
        );
    }
}
