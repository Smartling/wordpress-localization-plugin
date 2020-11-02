<?php
/**
 * Plugin Name: Smartling Example url href partial rewrite
 * Plugin URI: http://smartling.com
 * Author: Smartling
 * Version: 1.0
 */

/**
 * This example will rewrite 'en-US' to target locale in all found 'href' attributes of 'a' html elements
 */

use Smartling\Bootstrap;
use Smartling\Helpers\EventParameters\AfterDeserializeContentEventParameters;

add_action('plugins_loaded', static function () {
    // Smartling\Base\ExportedAPI::EVENT_SMARTLING_AFTER_DESERIALIZE_CONTENT
    add_action('smartling_after_deserialize_content', static function (AfterDeserializeContentEventParameters $params) {
        // This code will be executed every time when WP Connector applies translated content for every locale
        $search = 'en-US';
        $verifyContentExists = false; // If set to true, will make an http request to each replaced address to determine if content exists, this will considerably slow down the process of applying translation, but will not alter href if content is unreachable

        $logger = Bootstrap::getLogger();
        $localizationPluginProxy = Bootstrap::getContainer()->get('multilang.proxy');

        array_walk_recursive($params->getTranslatedFields(), static function (&$value) use ($localizationPluginProxy, $logger, $params, $search, $verifyContentExists) {
            $value = preg_replace_callback('~<a.*?href=(["\'])(.*?(' . $search . ').*?)\1.*?>~', static function ($matches) use ($localizationPluginProxy, $logger, $params, $verifyContentExists) {
                $replacement = $localizationPluginProxy->getBlogLocaleById($params->getSubmission()->getTargetBlogId());
                list($original, $_, $href, $search) = $matches;
                $result = str_replace($search, $replacement, $original);
                if ($verifyContentExists) {
                    try {
                        $code = (int)substr(get_headers(str_replace($search, $replacement, $href))[0], 9, 3);
                        if ($code > 399) {
                            $logger->debug(sprintf('URL_REPLACE: Skipping replacement of %s: unreachable content', $href));
                            return $original; // return unchanged tag
                        }
                    } catch (\Exception $e) {
                        $logger->debug(sprintf('URL_REPLACE: Failed getting headers for %s: %s', $result, $e->getMessage()));
                        return $original;
                    }
                }
                $logger->debug(sprintf("URL_REPLACE: Replacing '%s' with '%s'", $matches[0], $result));

                return $result;
            }, $value);
        });

        return $params;
    });
});
