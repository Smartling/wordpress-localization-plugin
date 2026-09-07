<?php

namespace Smartling\Helpers;

/**
 * Shared nonce + capability check for WordPress AJAX handlers (wp_ajax_* actions),
 * which combine both via check_ajax_referer(). For classes that render their own
 * wp_nonce_field() and verify it directly (WP_List_Table bulk actions, form-post
 * controllers), see NonceVerifier instead.
 */
class AjaxSecurityChecker
{
    use LoggerSafeTrait;

    public function __construct(private WordpressFunctionProxyHelper $wpProxy)
    {
    }

    /**
     * @return string|null An AjaxAuthorizationFailure::* constant on failure, null when authorized.
     */
    public function check(string $nonceAction, string $capability, string $actionName): ?string
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
    public function enforce(string $nonceAction, string $capability, string $actionName): bool
    {
        $authFailure = $this->check($nonceAction, $capability, $actionName);
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
