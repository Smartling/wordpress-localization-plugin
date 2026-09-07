<?php

namespace Smartling\Helpers;

/**
 * Shared CSRF nonce verification for classes that render their own wp_nonce_field()
 * and verify it directly against $_POST/$_REQUEST (WP_List_Table bulk actions,
 * form-post controllers). For WordPress AJAX handlers, which combine nonce and
 * capability checks via check_ajax_referer(), see AjaxSecurityChecker instead.
 */
class NonceVerifier
{
    public function __construct(private WordpressFunctionProxyHelper $wpProxy)
    {
    }

    /**
     * @param mixed $nonce Raw value read from the request; anything other than a non-empty string fails verification.
     */
    public function verify(mixed $nonce, string $nonceAction): bool
    {
        return is_string($nonce) && $nonce !== '' && false !== $this->wpProxy->wp_verify_nonce($nonce, $nonceAction);
    }
}
