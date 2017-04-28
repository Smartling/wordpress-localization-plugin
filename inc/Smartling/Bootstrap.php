<?php

namespace Smartling;

use Exception;
use Psr\Log\LoggerInterface;
use Smartling\Base\ExportedAPI;
use Smartling\ContentTypes\AutoDiscover\PostTypes;
use Smartling\ContentTypes\AutoDiscover\Taxonomies;
use Smartling\ContentTypes\ContentTypeNavigationMenu;
use Smartling\ContentTypes\ContentTypeNavigationMenuItem;
use Smartling\ContentTypes\ContentTypeWidget;
use Smartling\ContentTypes\CustomPostType;
use Smartling\ContentTypes\CustomTaxonomyType;
use Smartling\Exception\MultilingualPluginNotFoundException;
use Smartling\Exception\SmartlingBootException;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\MetaFieldProcessor\CustomFieldFilterHandler;
use Smartling\Helpers\SchedulerHelper;
use Smartling\Helpers\SimpleStorageHelper;
use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\Settings\SettingsManager;
use Smartling\WP\WPInstallableInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class Bootstrap
 * @package Smartling
 */
class Bootstrap
{

    use DebugTrait;
    use DITrait;

    public static $pluginVersion = 'undefined';

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
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $object = self::getContainer()->get('logger');

        if ($object instanceof LoggerInterface) {
            return $object;
        } else {
            $message = 'Something went wrong with initialization of DI Container and logger cannot be retrieved.';
            throw new SmartlingBootException($message);
        }
    }

    public static function getCurrentVersion()
    {
        return self::getContainer()->getParameter('plugin.version');
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

    public function activate()
    {
        $hooks = $this->fromContainer('hooks.installable', true);
        foreach ($hooks as $hook) {
            $object = $this->fromContainer($hook);
            if ($object instanceof WPInstallableInterface) {
                $object->activate();
            } else {

            }
        }
    }

    public function deactivate()
    {
        $hooks = $this->fromContainer('hooks.installable', true);
        foreach ($hooks as $hook) {
            $object = $this->fromContainer($hook);
            if ($object instanceof WPInstallableInterface) {
                $object->deactivate();
            }
        }
    }

    public static function uninstall()
    {
        $hooks = self::getContainer()->getParameter('hooks.installable');
        foreach ($hooks as $hook) {
            $object = self::getContainer()->get($hook);
            if ($object instanceof WPInstallableInterface) {
                $object->uninstall();
            }
        }
    }

    public function registerHooks()
    {
        /**
         * @var StartupRegisterManager $manager
         */
        $manager = $this->fromContainer('manager.register');

        $manager->registerServices();
    }

    public function load()
    {
        register_shutdown_function([$this, 'shutdownHandler']);

        $this->detectMultilangPlugins();

        self::getContainer()->setParameter('plugin.version', self::$pluginVersion);

        //always try to migrate db
        try {
            $this->fromContainer('site.db')->activate();
        } catch (\Exception $e) {
            self::getLogger()->error(vsprintf('Migration attempt finished with error: %s', [$e->getMessage()]));
        }

        try {
            if (defined('SMARTLING_CLI_EXECUTION') && SMARTLING_CLI_EXECUTION === false) {
                $this->test();
                $this->initializeContentTypes();
                $this->registerHooks();
                $this->run();
            }
        } catch (Exception $e) {
            $message = "Unhandled exception caught. Disabling plugin.\n";
            $message .= "Message: '" . $e->getMessage() . "'\n";
            $message .= "Location: '" . $e->getFile() . ':' . $e->getLine() . "'\n";
            $message .= "Trace: " . $e->getTraceAsString() . "\n";
            self::getLogger()->emergency($message);
            DiagnosticsHelper::addDiagnosticsMessage($message, true);
        }

        self::getContainer()->get('extension.loader')->runExtensions();
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

    /**
     * @throws MultilingualPluginNotFoundException
     */
    public function detectMultilangPlugins($scielent = true)
    {
        /**
         * @var LoggerInterface $logger
         */
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $logger = self::getLogger();

        $mlPluginsStatuses =
            [
                'multilingual-press-pro' => false,
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

        self::getContainer()->setParameter('multilang_plugins', $mlPluginsStatuses);
    }

    /**
     * Tests if current Wordpress Configuration can work with Smartling Plugin
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

        $this->testMinimalWordpressVersion();

        add_action('all_admin_notices', ['Smartling\Helpers\UiMessageHelper', 'displayMessages']);
    }

    protected function testMinimalWordpressVersion()
    {
        $minVersion = '4.6';
        if (version_compare(get_bloginfo('version'), $minVersion, '<')) {
            $msg = vsprintf('Wordpress has to be at least version %s to run smartlnig connector plugin. Please upgrade Your Wordpress installation.', [$minVersion]);
            self::getLogger()->critical('Boot :: ' . $msg);
            DiagnosticsHelper::addDiagnosticsMessage($msg, true);
        }
    }

    protected function testThirdPartyPluginsRequirements()
    {
        /**
         * @var array $data
         */
        $data = self::getContainer()->getParameter('multilang_plugins');

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
                           .
                           '<a href="' . get_site_url() . '/wp-admin/network/plugin-install.php?tab=search&s=multilingual+press">Multilingual Press.</a>';

            self::getLogger()->critical('Boot :: ' . $mainMessage);
            DiagnosticsHelper::addDiagnosticsMessage($mainMessage, true);
        } else {
            $data = SimpleStorageHelper::get('state_modules', false);
            $advTranslatorKey = 'class-Mlp_Advanced_Translator';
            if (is_array($data) && array_key_exists($advTranslatorKey, $data) && 'off' !== $data[$advTranslatorKey]) {
                $msg = '<strong>Advanced Translator</strong> feature of Multilingual Press plugin is currently turned on.<br/>
 Please turn it off to use Smartling-connector plugin. <br/> Use <a href="' . get_site_url() . '/wp-admin/network/settings.php?page=mlp"><strong>this link</strong></a> to visit Multilingual Press network settings page.';
                self::getLogger()->critical('Boot :: ' . $msg);
                DiagnosticsHelper::addDiagnosticsMessage($msg, true);
            }
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
                           .
                           '<a href="' . get_site_url() . '/wp-admin/admin.php?page=smartling_configuration_profile_list">settings page</a>';

            self::getLogger()->critical('Boot :: ' . $mainMessage);

            DiagnosticsHelper::addDiagnosticsMessage($mainMessage, true);
        }
    }

    /**
     * @param ContainerBuilder $di
     */
    private function initializeBuildInContentTypes(ContainerBuilder $di)
    {
        ContentTypeWidget::register($di);

        ContentTypeNavigationMenuItem::register($di);
        ContentTypeNavigationMenu::register($di);

        new Taxonomies($di);
        new PostTypes($di);

        $action = defined('DOING_CRON') && true === DOING_CRON ? 'wp_loaded' : 'admin_init';

        /**
         * Post types and taxonomies are registered on 'init' hook, but this code is executed on 'plugins_loaded' hook,
         * so we need to postpone dynamic handlers execution
         */
        add_action($action, function () use ($di) {

            // registering taxonomies first.
            $dynTermDefinitions = [];
            $dynTermDefinitions = apply_filters(ExportedAPI::FILTER_SMARTLING_REGISTER_CUSTOM_TAXONOMY, $dynTermDefinitions);
            foreach ($dynTermDefinitions as $dynTermDefinition) {
                CustomTaxonomyType::registerCustomType($di, $dynTermDefinition);
            }

            // then registering posts
            $externalDefinitions = [];
            $externalDefinitions = apply_filters(ExportedAPI::FILTER_SMARTLING_REGISTER_CUSTOM_POST_TYPE, $externalDefinitions);
            foreach ($externalDefinitions as $externalDefinition) {
                CustomPostType::registerCustomType($di, $externalDefinition);
            }

            // then registering filters
            $filters = [
                // categories may have parent
                [
                    'pattern'       => '^(parent)$',
                    'action'        => 'localize',
                    'serialization' => 'none',
                    'value'         => 'reference',
                    'type'          => 'category',
                ],
                // post-based content may have parent
                [
                    'pattern'       => '^(post_parent)$',
                    'action'        => 'localize',
                    'serialization' => 'none',
                    'value'         => 'reference',
                    'type'          => 'post',
                ],
                // featured images use _thumbnail_id meta key
                [
                    'pattern'       => '_thumbnail_id',
                    'action'        => 'localize',
                    'serialization' => 'none',
                    'value'         => 'reference',
                    'type'          => 'media',
                ],
            ];


            $filters = apply_filters(ExportedAPI::FILTER_SMARTLING_REGISTER_FIELD_FILTER, $filters);
            foreach ($filters as $filter) {
                try {
                    CustomFieldFilterHandler::registerFilter($di, $filter);
                } catch (\Exception $e) {
                    $di->get('logger')->warning(
                        vsprintf(
                            'Error registering filter with message: \'%s\', params: \'%s\'',
                            [
                                $e->getMessage(),
                                var_export($filter, true),
                            ]
                        )
                    );
                }
            }
        }, 999);
    }

    public function initializeContentTypes()
    {
        $this->initializeBuildInContentTypes(self::getContainer());
        do_action(ExportedAPI::ACTION_SMARTLING_REGISTER_CONTENT_TYPE, self::getContainer());
    }

    public function run()
    {
        $this->initRoles();
    }
}
