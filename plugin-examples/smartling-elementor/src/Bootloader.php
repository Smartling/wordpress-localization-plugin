<?php

namespace KPS3\Smartling\Elementor;

use Smartling\Bootstrap;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class Bootloader
{
    private ContainerBuilder $di;


    public function __construct(ContainerBuilder $di)
    {
        $this->di = $di;
    }

    private static function checkFunction(string $functionName, bool $strict = false, string $message = ''): bool
    {
        $result = function_exists($functionName);
        if (false === $result && true === $strict) {
            throw new \Exception($message);
        } else {
            return $result;
        }
    }

    /**
     * Displays error message for diagnostics
     */
    private static function displayErrorMessage(string $messageText = ''): void
    {
        if (self::checkFunction('add_action', true, 'This code cannot run outside of wordpress.')) {
            add_action('all_admin_notices', static function () use ($messageText) {
                echo vsprintf('<div class="error"><p>%s</p></div>', array($messageText));
            });
        }
    }

    private static function getPluginMeta(string $pluginFile, string $metaName): string
    {
        $pluginData = get_file_data($pluginFile, [$metaName => $metaName]);

        return $pluginData[$metaName];
    }

    private static function getPluginName(string $pluginFile): string
    {
        return self::getPluginMeta($pluginFile, 'Plugin Name');
    }

    private static function checkConnectorVersion(string $minVersion, string $maxVersion): bool
    {
        $realVersion = Bootstrap::$pluginVersion;
        $maxVersionParts = explode('.', $maxVersion);
        $realVersionParts = explode('.', $realVersion);
        $potentiallyNotSupported = false;
        foreach ($maxVersionParts as $index => $part) {
            if (!array_key_exists($index, $realVersionParts)) {
                return false; // misconfiguration
            }
            if ($realVersionParts[$index] > $part && $potentiallyNotSupported) {
                return false; // not supported
            }

            $potentiallyNotSupported = $realVersionParts[$index] === $part;
        }

        return version_compare($realVersion, $minVersion, '>=');
    }

    public static function boot(string $pluginFile, ContainerBuilder $di): void
    {
        [$minVersion, $maxVersion] = explode('-', self::getPluginMeta($pluginFile, 'SupportedConnectorVersions'));
        if (false === self::checkConnectorVersion($minVersion, $maxVersion)) {
            self::displayErrorMessage(
                vsprintf(
                    '<strong>%s</strong> extension plugin requires <strong>%s</strong> plugin version at least <strong>%s</strong> and at most <strong>%s</strong>.',
                    [self::getPluginName($pluginFile), 'Smartling Connector',
                     $minVersion,
                     $maxVersion]
                )
            );
        } else {
            require_once __DIR__ . DIRECTORY_SEPARATOR . 'ElementorDataSerializer.php';
            require_once __DIR__ . DIRECTORY_SEPARATOR . 'ElementorFilter.php';
            require_once __DIR__ . DIRECTORY_SEPARATOR . 'ElementorProcessor.php';
            require_once __DIR__ . DIRECTORY_SEPARATOR . 'ElementorFieldsFilterHelper.php';
            require_once __DIR__ . DIRECTORY_SEPARATOR . 'ElementorAutoSetup.php';
            (new static($di))->run();
        }


    }

    public function run(): void
    {
        ElementorAutoSetup::register($this->di);
    }
}
