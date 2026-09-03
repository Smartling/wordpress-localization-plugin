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
}
