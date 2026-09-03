<?php

namespace Smartling\Helpers;

/**
 * Shared nonce + capability check for WordPress AJAX handlers.
 *
 * Requires the using class to have a `WordpressFunctionProxyHelper $wpProxy`
 * property and a `getLogger()` method (e.g. via LoggerSafeTrait).
 */
trait AjaxSecurityTrait
{
    /**
     * Verifies the AJAX nonce and the current user's capability, logging on
     * failure. Callers are responsible for sending their own error response
     * based on the returned reason.
     *
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
     * Convenience wrapper around checkAjaxNonceAndCapability() for the common case: send the
     * standard wp_send_json_error() response on failure and let the caller just bail out.
     * Callers that need a different error payload shape (e.g. an error code field) should call
     * checkAjaxNonceAndCapability() directly instead.
     *
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
