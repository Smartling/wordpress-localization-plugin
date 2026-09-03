<?php

namespace Smartling\Helpers;

trait AjaxSecurityTrait
{
    /**
     * @return string|null An AjaxAuthorizationFailure::* constant on failure, null when authorized.
     */
    protected function checkAjaxNonceAndCapability(string $nonceAction, string $capability, string $actionName): ?string
    {
        if ($this->wpProxy->check_ajax_referer($nonceAction, '_wpnonce', false) === false) {
            $this->getLogger()->warning(sprintf(
                'Invalid nonce for action "%s" from userId=%d',
                $actionName,
                $this->wpProxy->get_current_user_id(),
            ));

            return AjaxAuthorizationFailure::INVALID_NONCE;
        }

        if (!$this->wpProxy->current_user_can($capability)) {
            $this->getLogger()->warning(sprintf(
                'User %d lacks capability "%s" for action "%s"',
                $this->wpProxy->get_current_user_id(),
                $capability,
                $actionName,
            ));

            return AjaxAuthorizationFailure::INSUFFICIENT_CAPABILITY;
        }

        return null;
    }

    /**
     * @return bool Whether the request is authorized. When false, an error response has already been sent.
     */
    protected function enforceAjaxAuthorization(string $nonceAction, string $capability, string $actionName): bool
    {
        $authFailure = $this->checkAjaxNonceAndCapability($nonceAction, $capability, $actionName);
        if ($authFailure === AjaxAuthorizationFailure::INVALID_NONCE) {
            $this->wpProxy->wp_send_json_error(['message' => 'Invalid nonce'], 403);

            return false;
        }
        if ($authFailure === AjaxAuthorizationFailure::INSUFFICIENT_CAPABILITY) {
            $this->wpProxy->wp_send_json_error(['message' => 'Insufficient permissions'], 403);

            return false;
        }

        return true;
    }
}
