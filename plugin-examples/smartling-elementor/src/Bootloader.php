<?php

namespace KPS3\Smartling\Elementor;

use Symfony\Component\DependencyInjection\ContainerBuilder;

class Bootloader {
    private const PLUGIN_NAME = 'Plugin Name';
    private const SUPPORTED_ELEMENTOR_VERSIONS = 'SupportedElementorVersions';
    private const SUPPORTED_SMARTLING_CONNECTOR_VERSIONS = 'SupportedSmartlingConnectorVersions';

    private ContainerBuilder $di;

    public function __construct(ContainerBuilder $di)
    {
        $this->di = $di;
    }

    private static function displayErrorMessage(string $messageText = ''): void
    {
        if (!function_exists('add_action')) {
            throw new \RuntimeException('This code cannot run outside of WordPress');
        }
        add_action('all_admin_notices', static function () use ($messageText) {
            echo "<div class=\"error\"><p>$messageText</p></div>";
        });
    }

    private static function getPluginMeta(string $pluginFile, string $metaName): string
    {
        $pluginData = get_file_data($pluginFile, [$metaName => $metaName]);

        return $pluginData[$metaName];
    }

    private static function getPluginName(string $pluginFile): string
    {
        return self::getPluginMeta($pluginFile, self::PLUGIN_NAME);
    }

    private static function versionInRange(string $version, string $minVersion, string $maxVersion): bool
    {
        $maxVersionParts = explode('.', $maxVersion);
        $versionParts = explode('.', $version);
        $potentiallyNotSupported = false;
        foreach ($maxVersionParts as $index => $part) {
            if (!array_key_exists($index, $versionParts)) {
                return false; // misconfiguration
            }
            if ($versionParts[$index] > $part && $potentiallyNotSupported) {
                return false; // not supported
            }

            $potentiallyNotSupported = $versionParts[$index] === $part;
        }

        return version_compare($version, $minVersion, '>=');
    }

    public static function boot(string $pluginFile, ContainerBuilder $di): void
    {
        $activePlugins = get_option('active_plugins');
        $allPlugins = get_plugins();
        $currentPluginName = self::getPluginName($pluginFile);
        foreach (
            [
                'Elementor' => self::SUPPORTED_ELEMENTOR_VERSIONS,
                'Smartling Connector' => self::SUPPORTED_SMARTLING_CONNECTOR_VERSIONS,
            ] as $pluginName => $metaName) {
            if (!in_array($pluginName, $activePlugins, true)) {
                self::displayErrorMessage("<strong>$currentPluginName</strong> extension plugin requires active <strong>$pluginName</strong>");
                return;
            }
            [$minVersion, $maxVersion] = explode('-', self::getPluginMeta($pluginFile, $metaName));
            if (!self::versionInRange($allPlugins[$pluginName]['Version'] ?? '0', $minVersion, $maxVersion)) {
                self::displayErrorMessage("<strong>$currentPluginName</strong> extension plugin requires <strong>$pluginName</strong> plugin version at least <strong>$minVersion</strong> and at most <strong>$maxVersion</strong>");
                return;
            }
        }

        require_once __DIR__ . DIRECTORY_SEPARATOR . 'ElementorDataSerializer.php';
        require_once __DIR__ . DIRECTORY_SEPARATOR . 'ElementorFilter.php';
        require_once __DIR__ . DIRECTORY_SEPARATOR . 'ElementorProcessor.php';
        require_once __DIR__ . DIRECTORY_SEPARATOR . 'ElementorFieldsFilterHelper.php';
        require_once __DIR__ . DIRECTORY_SEPARATOR . 'ElementorAutoSetup.php';
        (new static($di))->run();
    }

    public function run(): void
    {
        ElementorAutoSetup::register($this->di);
    }
}
