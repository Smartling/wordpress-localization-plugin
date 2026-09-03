<?php

namespace Smartling\Helpers;

/**
 * Failure reasons returned by AjaxSecurityTrait::checkAjaxNonceAndCapability().
 *
 * Kept as a plain class rather than constants on the trait itself: trait
 * constants require PHP 8.2, and this project targets PHP 8.0.
 */
final class AjaxAuthorizationFailure
{
    public const INVALID_NONCE = 'invalid_nonce';

    public const INSUFFICIENT_CAPABILITY = 'insufficient_capability';
}
