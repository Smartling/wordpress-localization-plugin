<?php

namespace Smartling;

use Exception;
use JetBrains\PhpStorm\NoReturn;
use Smartling\Base\ExportedAPI;
use Smartling\ContentTypes\AutoDiscover\PostTypes;
use Smartling\ContentTypes\AutoDiscover\Taxonomies;
use Smartling\ContentTypes\ContentTypeNavigationMenu;
use Smartling\ContentTypes\ContentTypeNavigationMenuItem;
use Smartling\ContentTypes\ContentTypeWidget;
use Smartling\ContentTypes\CustomPostType;
use Smartling\ContentTypes\CustomTaxonomyType;
use Smartling\ContentTypes\ExternalContentManager;
use Smartling\Exception\SmartlingBootException;
use Smartling\Exception\SmartlingConfigException;
use Smartling\Extensions\Acf\AcfDynamicSupport;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\MetaFieldProcessor\CustomFieldFilterHandler;
use Smartling\Helpers\PluginHelper;
use Smartling\Helpers\SchedulerHelper;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\Helpers\UiMessageHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Services\GlobalSettingsManager;
use Smartling\Services\LocalizationPluginProxyCollection;
use Smartling\Vendor\Psr\Log\LoggerInterface;
use Smartling\Vendor\Symfony\Component\DependencyInjection\ContainerBuilder;
use Smartling\WP\Controller\ConfigurationProfilesController;
use Smartling\WP\WPInstallableInterface;

class Bootstrap
{
    use DebugTrait;
    use DITrait;

    private const WORDPRESS_ORG_PLUGIN_NAME = 'smartling-connector';

    public static LoggerInterface $loggerInstance;

    public static string $pluginVersion = 'undefined';

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
     * @throws SmartlingBootException
     */
    public static function getLogger(): LoggerInterface
    {
        $object = MonologWrapper::getLogger(get_called_class());

        if ($object instanceof LoggerInterface) {
            return $object;
        }

        $message = 'Something went wrong with initialization of DI Container and logger cannot be retrieved.';
        throw new SmartlingBootException($message);
    }

    public static function getCurrentVersion()
    {
        return static::getContainer()->getParameter('plugin.version');
    }

    public function activate(): void
    {
        $hooks = $this->fromContainer('hooks.installable', true);
        foreach ($hooks as $hook) {
            $object = $this->fromContainer($hook);
            if ($object instanceof WPInstallableInterface) {
                $object->activate();
            }
        }
    }

    public function deactivate(): void
    {
        $hooks = $this->fromContainer('hooks.installable', true);
        foreach ($hooks as $hook) {
            $object = $this->fromContainer($hook);
            if ($object instanceof WPInstallableInterface) {
                $object->deactivate();
            }
        }
    }

    public static function uninstall(): void
    {
        $hooks = static::getContainer()->getParameter('hooks.installable');
        foreach ($hooks as $hook) {
            $object = static::getContainer()->get($hook);
            if ($object instanceof WPInstallableInterface) {
                $object->uninstall();
            }
        }
    }

    private function registerHooks(): void
    {
        /**
         * @var StartupRegisterManager $manager
         */
        $manager = $this->fromContainer('manager.register');

        $manager->registerServices();
    }

    /**
     * The initial entry point tor plugins_loaded hook
     */
    public function load(): void
    {
        register_shutdown_function([$this, 'shutdownHandler']);

        static::getContainer()->setParameter('plugin.version', static::$pluginVersion);

        //always try to migrate db
        try {
            $this->fromContainer('site.db')->activate();
        } catch (\Exception $e) {
            static::getLogger()->error(vsprintf('Migration attempt finished with error: %s', [$e->getMessage()]));
        }

        try {
            $this->setMultiLanguageProxy($this->fromContainer('localization.plugin.proxy.collection'));
            $this->test();
            $this->initializeContentTypes();
            $this->registerHooks();
            $this->initRoles();
        } catch (Exception $e) {
            $message = "Unhandled exception caught. Disabling plugin.\n";
            $message .= "Message: '" . $e->getMessage() . "'\n";
            $message .= "Location: '" . $e->getFile() . ':' . $e->getLine() . "'\n";
            $message .= "Trace: " . $e->getTraceAsString() . "\n";
            static::getLogger()->emergency($message);
            DiagnosticsHelper::addDiagnosticsMessage($message, true);
        }

        static::getContainer()->get('extension.loader')->runExtensions();
    }


    /**
     * Add smartling capabilities to 'administrator' role by default
     */
    private function initRoles(): void
    {
        $role = get_role('administrator');

        if ($role instanceof \WP_Role) {
            foreach (SmartlingUserCapabilities::$CAPABILITY as $capability) {
                $role->add_cap($capability, true);
            }
        } else {
            /**
             * @var SiteHelper $siteHelper
             */
            $siteHelper = static::getContainer()->get('site.helper');
            $msg = vsprintf('\'administrator\' role doesn\'t exists in site id=%s', [$siteHelper->getCurrentBlogId()]);
            static::getLogger()->warning($msg);
        }
    }

    #[NoReturn]
    public function updateGlobalExpertSettings(): void
    {
        $data = $_POST['params'];

        $rawPageSize = (int)$data['pageSize'];

        $pageSize = $rawPageSize < 1 ? GlobalSettingsManager::getPageSizeDefault() : $rawPageSize;

        GlobalSettingsManager::setAddSlashesBeforeSavingContent($data[GlobalSettingsManager::SETTING_ADD_SLASHES_BEFORE_SAVING_CONTENT]);
        GlobalSettingsManager::setAddSlashesBeforeSavingMeta($data[GlobalSettingsManager::SETTING_ADD_SLASHES_BEFORE_SAVING_META]);
        GlobalSettingsManager::setCustomDirectives(stripslashes($data[GlobalSettingsManager::SETTING_CUSTOM_DIRECTIVES]));
        GlobalSettingsManager::setRemoveAcfParseSaveBlocksFilter($data[GlobalSettingsManager::SETTING_REMOVE_ACF_PARSE_SAVE_BLOCKS_FILTER]);
        GlobalSettingsManager::setSkipSelfCheck((int)$data['selfCheckDisabled']);
        GlobalSettingsManager::setDisableLogging((int)$data['disableLogging']);
        GlobalSettingsManager::setLogFileSpec($data['loggingPath']);
        GlobalSettingsManager::setPageSize($pageSize);
        GlobalSettingsManager::setLoggingCustomization($data['loggingCustomization']);
        GlobalSettingsManager::setGenerateLockIdsFrontend($data[GlobalSettingsManager::SMARTLING_FRONTEND_GENERATE_LOCK_IDS]);
        GlobalSettingsManager::setRelatedContentSelectState((int)$data[GlobalSettingsManager::SMARTLING_RELATED_CONTENT_SELECT_STATE]);
        GlobalSettingsManager::setFilterUiVisible((int)$data['enableFilterUI']);

        wp_send_json($data);
    }

    /**
     * Tests if current Wordpress Configuration can work with Smartling Plugin
     */
    private function test(): void
    {
        $phpExtensions = [
            'curl',
            'mbstring',
        ];

        foreach ($phpExtensions as $ext) {
            $this->testPhpExtension($ext);
        }

        $this->testExternalHandlers();
        $this->testPluginSetup();
        $this->testWordpress();
        $this->testTimeLimit();

        if (current_user_can(SmartlingUserCapabilities::SMARTLING_CAPABILITY_WIDGET_CAP)) {
            add_action('wp_ajax_' . 'smartling_expert_global_settings_update', [$this, 'updateGlobalExpertSettings']);
        }

        if (0 === (int)GlobalSettingsManager::getSkipSelfCheck()) {
            $this->testCronSetup();
            $this->testUpdates();
        }

        add_action('wp_ajax_' . UiMessageHelper::DISMISS_MESSAGE_ACTION, [UiMessageHelper::class, 'dismissMessage']);
        add_action('admin_notices', [UiMessageHelper::class, 'displayMessages']);
    }

    private function testTimeLimit(int $recommended = 300): void
    {
        $timeLimit = ini_get('max_execution_time');

        if (0 !== (int)$timeLimit && $recommended >= $timeLimit) {
            $mainMessage = vsprintf(
                '<strong>Smartling-connector</strong> configuration is not optimal.<br /><strong>max_execution_time</strong> is highly recommended to be set at least %s. Current value is %s',
                [$recommended, $timeLimit]
            );

            static::$loggerInstance->warning($mainMessage);

            DiagnosticsHelper::addDiagnosticsMessage($mainMessage, false);
        }
    }

    private function testCronSetup(): void
    {
        if (!defined('DISABLE_WP_CRON') || true !== DISABLE_WP_CRON) {
            $logMessage = 'Cron doesn\'t seem to be configured.';
            static::getLogger()->warning($logMessage);
            if (current_user_can('manage_network_plugins')) {
                $mainMessage = '<strong>Smartling-connector</strong> configuration is not optimal.<br />Warning! WordPress cron installation is not configured properly. Please follow configuration steps described <a target="_blank" href="https://help.smartling.com/hc/en-us/articles/4405135381915-Configure-Expert-Settings-WordPress-Cron-for-the-WordPress-Connector-">here</a>';
                DiagnosticsHelper::addDiagnosticsMessage($mainMessage, false);
            }
        }
    }

    private function testExternalHandlers(): void
    {
        $externalContentManager = $this->fromContainer('manager.content.external');
        $externalContentPluginPaths = [];
        $messages = [];
        $pluginHelper = $this->fromContainer('helper.plugins');
        $unsupportedPluginPaths = [];
        $wpProxy = $this->fromContainer('wp.proxy');
        if (!$externalContentManager instanceof ExternalContentManager) {
            throw new SmartlingConfigException('Expected to get ' . ExternalContentManager::class . ' from container, got ' . get_class($externalContentManager));
        }
        if (!$pluginHelper instanceof PluginHelper) {
            throw new SmartlingConfigException('Expected to get ' . PluginHelper::class . ' from container, got ' . get_class($externalContentManager));
        }
        if (!$wpProxy instanceof WordpressFunctionProxyHelper) {
            throw new SmartlingConfigException('Expected to get ' . WordpressFunctionProxyHelper::class . ' from container, got ' . get_class($externalContentManager));
        }
        $handlers = $externalContentManager->getHandlers();
        foreach ($handlers as $handler) {
            $externalContentPluginPaths[] = $handler->getPluginPaths();
        }
        $externalContentPluginPaths = array_merge(...$externalContentPluginPaths);
        foreach (array_unique($externalContentPluginPaths) as $pluginPath) {
            foreach ($handlers as $handler) {
                if (!$wpProxy->is_plugin_active($pluginPath) || $pluginHelper->pluginVersionInRange($pluginPath, $handler->getMinVersion(), $handler->getMaxVersion())) {
                    continue 2;
                }
            }
            $unsupportedPluginPaths[] = $pluginPath;
        }
        if (count($unsupportedPluginPaths) > 0) {
            $supportedPluginVersions = [];
            foreach ($unsupportedPluginPaths as $pluginPath) {
                foreach ($handlers as $handler) {
                    if (!array_key_exists($pluginPath, $supportedPluginVersions)) {
                        $supportedPluginVersions[$pluginPath] = [];
                    }
                    if (in_array($pluginPath, $handler->getPluginPaths(), true)) {
                        $supportedPluginVersions[$pluginPath][] = $handler->getMinVersion() . '-' . $handler->getMaxVersion();
                    }
                }
            }
            $plugins = $wpProxy->get_plugins();
            foreach ($supportedPluginVersions as $pluginPath => $versions) {
                $messages[] = ($plugins[$pluginPath]['Name'] ?? 'Undefined plugin (' . $pluginPath . ')') . ' version ' . ($plugins[$pluginPath]['Version'] ?? 'undefined') . ' (supported versions: ' . implode(', ', $versions) . ')';
            }
            DiagnosticsHelper::addDiagnosticsMessage('Unsupported plugin version detected: ' . implode(', ', $messages));
        }
    }

    private function testWordpress(): void
    {
        $minVersion = '5.5';
        if (version_compare(get_bloginfo('version'), $minVersion, '<')) {
            $msg = vsprintf('Wordpress has to be at least version %s to run smartling connector plugin. Please upgrade Your Wordpress installation.', [$minVersion]);
            static::getLogger()->critical('Boot :: ' . $msg);
            DiagnosticsHelper::addDiagnosticsMessage($msg, true);
        }
        if (!function_exists('get_sites')) {
            DiagnosticsHelper::addDiagnosticsMessage('WordPress needs to be in multisite mode to run Smartling connector plugin. Please, <a href="https://wordpress.org/support/article/create-a-network/">create a network</a>.', true);
        }
    }

    private function setMultiLanguageProxy(LocalizationPluginProxyCollection $connectorPlugins): void
    {
        $containerBuilder = self::getContainer();
        $plugin = $connectorPlugins->getActivePlugin();
        $containerBuilder->register('multilang.proxy', get_class($plugin));
        $plugin->addHooks();
    }

    private function testPhpExtension(string $extension): void
    {
        if (!extension_loaded($extension)) {
            $mainMessage = $extension . ' php extension is required to run the plugin is not installed or enabled.';

            static::$loggerInstance->critical('Boot :: ' . $mainMessage);

            DiagnosticsHelper::addDiagnosticsMessage($mainMessage, true);
        }
    }

    private function testUpdates(): void
    {
        $cur_version = static::$pluginVersion;
        $new_version = '0.0.0';

        $info = get_site_transient('update_plugins');
        if (is_object($info) && isset($info->response)) {
            $response = $info->response;
            if (is_array($response)) {
                foreach ($response as $definition) {
                    if (self::WORDPRESS_ORG_PLUGIN_NAME !== $definition->slug) {
                        continue;
                    }
                    $new_version = $definition->new_version;
                    break;
                }
            }
        } else {
            static::getLogger()->debug('No cached information found about updates. Requesting info...');
            if (!function_exists('plugins_api')) {
                require_once(ABSPATH . 'wp-admin/includes/plugin-install.php');
            }
            $args = ['slug' => self::WORDPRESS_ORG_PLUGIN_NAME, 'fields' => ['version' => true]];
            $response = plugins_api('plugin_information', $args);

            if (is_wp_error($response)) {
                static::getLogger()
                    ->error(vsprintf('Updates information request ended with error: %s', [$response->get_error_message()]));
            } else {
                $new_version = $response->version;
            }
        }

        if (version_compare($new_version, $cur_version, '>')) {
            $mainMessage = vsprintf(
                'A new version <strong>%s</strong> of Smartling Connector plugin is available for download. Current version is %s. Please update plugin <a href="%s">here</a>.',
                [
                    $new_version, $cur_version,
                    site_url('/wp-admin/network/plugins.php?s=smartling+connector&plugin_status=all'),
                ]);

            static::$loggerInstance->debug($mainMessage);
            DiagnosticsHelper::addDiagnosticsMessage($mainMessage, false);
        }
    }

    private function testPluginSetup(): void
    {
        $sm = static::getContainer()->get('manager.settings');

        $total = 0;
        $profiles = $sm->getEntities($total, true);

        if (0 === count($profiles)) {
            $mainMessage = 'No active smartling configuration profiles found. Please create at least one on '
                           .
                           '<a href="' . get_site_url() .
                           '/wp-admin/admin.php?page=' . ConfigurationProfilesController::MENU_SLUG . '">settings page</a>';

            static::getLogger()->critical('Boot :: ' . $mainMessage);

            DiagnosticsHelper::addDiagnosticsMessage($mainMessage, true);
        }
    }

    private function initializeBuildInContentTypes(ContainerBuilder $di): void
    {
        ContentTypeWidget::register($di);

        ContentTypeNavigationMenuItem::register($di);
        ContentTypeNavigationMenu::register($di);

        (new Taxonomies($di->getParameter('ignoredTypes')['taxonomies'] ?? []))->registerHookHandler();
        (new PostTypes($di->getParameter('ignoredTypes')['posts'] ?? []))->registerHookHandler();

        $action = defined('DOING_CRON') && true === DOING_CRON ? 'wp_loaded' : 'admin_init';

        add_action($action, function () {
            /**
             * Initializing ACF and ACF Option Pages support.
             */
            $acf = $this->fromContainer('acf.dynamic.support');
            assert($acf instanceof AcfDynamicSupport);
            $acf->run();
        });

        /**
         * Post types and taxonomies are registered on 'init' hook, but this code is executed on 'plugins_loaded' hook,
         * so we need to postpone dynamic handlers execution
         */
        add_action($action, static function () use ($action, $di) {
            // registering taxonomies first.
            $dynTermDefinitions = apply_filters(ExportedAPI::FILTER_SMARTLING_REGISTER_CUSTOM_TAXONOMY, []);
            if (!is_iterable($dynTermDefinitions)) {
                self::$loggerInstance->critical('Dynamic term definitions not iterable after filter ' . ExportedAPI::FILTER_SMARTLING_REGISTER_CUSTOM_TAXONOMY . '. This is most likely due to an error outside of the plugins code.');
            }
            foreach ($dynTermDefinitions as $dynTermDefinition) {
                CustomTaxonomyType::registerCustomType($di, $dynTermDefinition);
            }

            // then registering posts
            $externalDefinitions = apply_filters(ExportedAPI::FILTER_SMARTLING_REGISTER_CUSTOM_POST_TYPE, []);
            if (!is_iterable($externalDefinitions)) {
                self::$loggerInstance->critical('External definitions not iterable after filter ' . ExportedAPI::FILTER_SMARTLING_REGISTER_CUSTOM_POST_TYPE . '. This is most likely due to an error outside of the plugins code.');
            }
            $registeredTypes = array_column(array_column($externalDefinitions, 'type'), 'identifier');
            self::$loggerInstance->debug('Registered custom post types: ' .
                implode(', ', $registeredTypes) . ", action=$action, count=" . count($registeredTypes));
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
            if (!is_iterable($filters)) {
                self::$loggerInstance->critical('Filters not iterable after filter ' . ExportedAPI::FILTER_SMARTLING_REGISTER_FIELD_FILTER . '. This is most likely due to an error outside of the plugins code.');
            }
            foreach ($filters as $filter) {
                try {
                    CustomFieldFilterHandler::registerFilter($di, $filter);
                } catch (\Exception $e) {
                    static::getLogger()->warning(
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

    private function initializeContentTypes(): void
    {
        $this->initializeBuildInContentTypes(static::getContainer());
        do_action(ExportedAPI::ACTION_SMARTLING_REGISTER_CONTENT_TYPE, static::getContainer());
    }
}
