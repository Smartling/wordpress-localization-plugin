<?php

namespace Smartling;

use Exception;
use Psr\Log\LoggerInterface;
use Smartling\Exception\MultilingualPluginNotFoundException;
use Smartling\Exception\SmartlingBootException;
use Smartling\Exception\SmartlingConfigException;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\SchedulerHelper;
use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\Settings\SettingsManager;
use Smartling\WP\WPHookInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class Bootstrap
 *
 * @package Smartling
 */
class Bootstrap
{

    use DebugTrait;
    use DITrait;

    public function __construct()
    {
        ignore_user_abort(true);
        set_time_limit(0);

        $scheduleHelper = new SchedulerHelper();
        add_filter('cron_schedules', [$scheduleHelper, 'extendWpCron']);
    }

    public static function getHttpHostName()
    {
        $url = network_site_url();
        $parts = parse_url($url);

        return $parts['host'];
    }

    /**
     * @var LoggerInterface
     */
    private static $loggerInstance = null;

    /**
     * @return LoggerInterface
     * @throws SmartlingBootException
     */
    public static function getLogger()
    {
        $object = self::getContainer()
                      ->get('logger');

        if ($object instanceof LoggerInterface) {
            return $object;
        } else {
            $message = 'Something went wrong with initialization of DI Container and logger cannot be retrieved.';
            throw new SmartlingBootException($message);
        }
    }

    public static function getCurrentVersion()
    {
        return self::getContainer()
                   ->getParameter('plugin.version');
    }


    private static function setCoreParameters(ContainerBuilder $container)
    {
        // plugin dir (to use in config file)
        $container->setParameter('plugin.dir', SMARTLING_PLUGIN_DIR);
        $container->setParameter('plugin.upload', SMARTLING_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'upload');

        $pluginUrl = '';
        if (defined('SMARTLING_CLI_EXECUTION') && false === SMARTLING_CLI_EXECUTION) {
            $pluginUrl = plugin_dir_url(SMARTLING_PLUGIN_DIR . DIRECTORY_SEPARATOR . '..');
        }

        $container->setParameter('plugin.url', $pluginUrl);
    }

    public function registerHooks()
    {
        $hooks = $this->fromContainer('wp.hooks', true);
        foreach ($hooks as $hook) {
            $object = $this->fromContainer($hook);
            if ($object instanceof WPHookInterface) {
                $object->register();
            }
        }
    }

    public function load()
    {
        register_shutdown_function([$this, 'shutdownHandler']);

        $this->detectMultilangPlugins();

        //always try to migrate db
        $this->fromContainer('site.db')
             ->install();

        try {
            if (defined('SMARTLING_CLI_EXECUTION') && SMARTLING_CLI_EXECUTION === false) {
                $this->test();
                $this->registerHooks();
                $this->run();
            }
        } catch (Exception $e) {
            $message = "Unhandled exception caught. Disabling plugin.\n";
            $message .= "Message: '" . $e->getMessage() . "'\n";
            $message .= "Location: '" . $e->getFile() . ':' . $e->getLine() . "'\n";
            $message .= "Trace: " . $e->getTraceAsString() . "\n";
            self::getLogger()
                ->emergency($message);
            DiagnosticsHelper::addDiagnosticsMessage($message, true);
        }

        self::getContainer()
            ->get('extension.loader')
            ->runExtensions();
    }


    /**
     * Add smartling capabilities to 'administrator' role by default
     */
    private function initRoles()
    {
        $role = get_role('administrator');

        foreach (SmartlingUserCapabilities::$CAPABILITY as $capability) {
            $role->add_cap($capability, true);
        }
    }

    public function activate()
    {
        self::getContainer()
            ->set('multilang_plugins', []);
        $this->fromContainer('site.db')
             ->install();
        $this->fromContainer('wp.cron')
             ->install();
    }

    public function deactivate()
    {
        $this->fromContainer('wp.cron')
             ->uninstall();
    }

    public function uninstall()
    {
        if (defined('SMARTLING_COMPLETE_REMOVE')) {
            $this->fromContainer('site.db')
                 ->uninstall();
        }
    }

    /**
     * @throws MultilingualPluginNotFoundException
     */
    public function detectMultilangPlugins($scielent = true)
    {
        /**
         * @var LoggerInterface $logger
         */
        $logger = self::getContainer()
                      ->get('logger');

        $mlPluginsStatuses =
            [
                'multilingual-press-pro' => false,
                'polylang'               => false,
            ];

        $found = false;

        if (class_exists('Mlp_Load_Controller', false)) {
            $mlPluginsStatuses['multilingual-press-pro'] = true;
            $found = true;
        }

        if (false === $found) {
            $message = 'No active multilingual plugins found.';
            $logger->warning($message);
            if (!$scielent) {
                throw new MultilingualPluginNotFoundException($message);
            }
        }

        self::getContainer()
            ->setParameter('multilang_plugins', $mlPluginsStatuses);
    }

    public function checkUploadFolder()
    {
        $path = self::getContainer()
                    ->getParameter('plugin.upload');
        if (!file_exists($path)) {
            mkdir($path, 0777);
        }
    }

    /**
     * Tests if current Wordpress Configuration can work with Smartling Plugin
     *
     * @return mixed
     */
    protected function test()
    {
        $this->testThirdPartyPluginsRequirements();

        $phpExtensions = [
            'curl',
            'mbstring',
        ];

        foreach ($phpExtensions as $ext) {
            $this->testPhpExtension($ext);
        }

        $this->testPluginSetup();

        add_action('all_admin_notices', ['Smartling\Helpers\UiMessageHelper', 'displayMessages']);
    }

    protected function testThirdPartyPluginsRequirements()
    {
        /**
         * @var array $data
         */
        $data = self::getContainer()
                    ->getParameter('multilang_plugins');

        $blockWork = true;

        foreach ($data as $value) {
            // there is at least one plugin that can be used
            if (true === $value) {
                $blockWork = false;
                break;
            }
        }

        if (true === $blockWork) {
            $mainMessage = 'No active suitable localization plugin found. Please install and activate one, e.g.: '
                . '<a href="/wp-admin/network/plugin-install.php?tab=search&s=multilingual+press">Multilingual Press.</a>';

            self::getLogger()
                ->critical('Boot :: ' . $mainMessage);

            DiagnosticsHelper::addDiagnosticsMessage($mainMessage, true);
        }
    }

    protected function testPhpExtension($extension)
    {
        if (!extension_loaded($extension)) {
            $mainMessage = $extension . ' php extension is required to run the plugin is not installed or enabled.';

            self::$loggerInstance->critical('Boot :: ' . $mainMessage);

            DiagnosticsHelper::addDiagnosticsMessage($mainMessage, true);
        }
    }

    protected function testPluginSetup()
    {
        /**
         * @var SettingsManager $sm
         */
        $sm = self::getContainer()
                  ->get('manager.settings');

        $total = 0;
        $profiles = $sm->getEntities([], null, $total, true);

        if (0 === count($profiles)) {
            $mainMessage = 'No active smartling configuration profiles found. Please create at least one on '
                . '<a href="/wp-admin/admin.php?page=smartling_configuration_profile_list">settings page</a>';

            self::getLogger()
                ->critical('Boot :: ' . $mainMessage);

            DiagnosticsHelper::addDiagnosticsMessage($mainMessage, true);
        }
    }

    public function run()
    {
        $this->checkUploadFolder();
        $this->initRoles();
    }
}
