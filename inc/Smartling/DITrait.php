<?php

namespace Smartling;

use Monolog\Handler\NullHandler;
use Smartling\Base\ExportedAPI;
use Smartling\Exception\SmartlingConfigException;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\LogContextMixinHelper;
use Smartling\Helpers\SimpleStorageHelper;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Services\GlobalSettingsManager;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

trait DITrait
{
    /**
     * @var ContainerBuilder $container
     */
    private static $containerInstance = null;

    /**
     * Initializes DI Container from YAML config file
     * @throws SmartlingConfigException
     */
    protected static function initContainer()
    {
        $container = new ContainerBuilder();

        self::setCoreParameters($container);

        $configDir = [SMARTLING_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'config'];

        $fileLocator = new FileLocator($configDir);

        $loader = new YamlFileLoader($container, $fileLocator);

        try {
            $loader->load('boot.yml');
            $configFiles = $container->getParameter('config.files');
            foreach ($configFiles as $configFile) {
                $loader->load($configFile);
            }
        } catch (\Exception $e) {
            throw new SmartlingConfigException('Error in YAML configuration file', 0, $e);
        }

        self::$containerInstance = $container;

        self::injectLoggerCustomizations(self::$containerInstance);
        self::handleLoggerConfiguration();

        self::registerCloudLogExtension(self::$containerInstance);

        /**
         * Exposing reference to DI interface
         */
        do_action(ExportedAPI::ACTION_SMARTLING_BEFORE_INITIALIZE_EVENT, self::$containerInstance);

        // Init monolog wrapper when all the modifications to DI container
        // are done.
        MonologWrapper::init(self::$containerInstance);

        $logger = MonologWrapper::getLogger(__CLASS__);

        $host = false === gethostname() ? 'unknown' : gethostname();
        LogContextMixinHelper::addToContext('host', $host);
        LogContextMixinHelper::addToContext('http_host', $_SERVER['HTTP_HOST']);
        LogContextMixinHelper::addToContext('phpVersion', PHP_VERSION);
        LogContextMixinHelper::addToContext('pluginVersion', static::$pluginVersion);

        self::$loggerInstance = $logger;
    }

    private static function registerCloudLogExtension(ContainerBuilder $di)
    {
        $defSmartling = $di->register('smartlingLogFileHandler','\Smartling\Base\SmartlingLogHandler')
            ->addArgument('https://api.smartling.com/updates/status');

        $defBuffer = $di->register('bufferHandler', '\Monolog\Handler\BufferHandler')
            ->addArgument($defSmartling)
            ->addArgument(1000)
            ->addArgument('DEBUG')
            ->addArgument(true)
            ->addArgument(true);

        foreach ($di->getDefinitions() as $serviceId => $serviceDefinition) {
            if ($serviceDefinition->getClass() === 'Smartling\MonologWrapper\Logger\LevelLogger') {
                $serviceDefinition->addMethodCall('pushHandler',[$defBuffer]);
            }
        };
    }

    private static function injectLoggerCustomizations(ContainerBuilder $di)
    {
        $storedConfiguration = GlobalSettingsManager::getLoggingCustomization();

        $allowedLevels = [
            'debug',
            'info',
            'notice',
            'warning',
            'error',
            'critical',
            'alert',
            'emergency',
        ];
        $handler = $di->getDefinition('fileLoggerHandlerRotatable');

        if (is_array($storedConfiguration) && 0 < count($storedConfiguration)) {
            foreach ($storedConfiguration as $level => $items) {
                if (!in_array($level, $allowedLevels, true)) {
                    $msg = vsprintf('Found invalid logger configuration block \'%s\', with values: %s skipping...', [$level,
                                                                                                                     var_export($items, true)]);
                    MonologWrapper::getLogger(__CLASS__)->warning($msg);
                    DiagnosticsHelper::addDiagnosticsMessage($msg);
                    continue;
                }

                if (is_array($items)) {
                    foreach ($items as $item) {
                        $item = stripslashes($item);
                        $defId = vsprintf('logger.%s.%s', [$level, md5($item)]);

                        $di->register($defId, 'Smartling\MonologWrapper\Logger\LevelLogger')
                            ->addArgument($item)
                            ->addArgument($level)
                            ->addArgument([$handler]);
                    }
                }
            }
        }
    }

    private static function handleLoggerConfiguration()
    {
        add_action(ExportedAPI::ACTION_SMARTLING_BEFORE_INITIALIZE_EVENT, function (ContainerBuilder $di) {
            $di->setParameter('logger.filehandler.standard.filename', GlobalSettingsManager::getLogFileSpec());
            $di->setParameter('submission.pagesize', GlobalSettingsManager::getPageSize());
            if (1 === (int) GlobalSettingsManager::getDisableLogging()) {
                Bootstrap::disableLogging($di);
            }
        }, 8);

        add_action(ExportedAPI::ACTION_SMARTLING_BEFORE_INITIALIZE_EVENT, function (ContainerBuilder $di) {
            $file = self::getLogFileName();
            if (file_exists($file) && !is_writable($file)) {
                if (1 === (int) GlobalSettingsManager::getDisableLogging()) {
                    return;
                }
                add_action('admin_init', function () {
                    $msg = [
                        '<strong>Warning!</strong>',
                        vsprintf('It is not possible to write runtime logs into a file <strong>%s</strong>.', [Bootstrap::getLogFileName()]),
                        'It\'s highly important to have a log file in case of troubleshooting issues with translations.',
                        vsprintf('Please review <a href="%s">logger configuration</a> and fix it.', [admin_url('admin.php?page=smartling_configuration_profile_list')]),
                    ];
                    DiagnosticsHelper::addDiagnosticsMessage(implode('<br/>', $msg));
                });
            }
        }, 9);
    }

    private static function nullLog(ContainerBuilder $di)
    {
        // Disable all defined loggers.
        foreach ($di->getDefinitions() as $serviceId => $serviceDefinition) {
            if ($serviceDefinition->getClass() == 'Smartling\MonologWrapper\Logger\LevelLogger') {
                $di->get($serviceId)->setHandlers([new NullHandler()]);
            }
        };
    }

    public static function disableLogging(ContainerBuilder $di)
    {
        self::nullLog($di);
        add_action('admin_init', function () {
            $msg = [
                '<strong>Warning!</strong>',
                'Logging is completely disabled. Previous log files are untouched.',
                'It\'s highly important to have a log file in case of troubleshooting issues with translations.',
            ];
            DiagnosticsHelper::addDiagnosticsMessage(implode('<br/>', $msg));
        });
    }

    /**
     * Extracts mixed from container
     *
     * @param string $id
     * @param bool   $isParam
     *
     * @return mixed
     */
    protected function fromContainer($id, $isParam = false)
    {
        $container = self::getContainer();
        $content = null;

        if ($isParam) {
            $content = $container->getParameter($id);
        } else {
            $content = $container->get($id);
        }

        return $content;
    }

    /**
     * @return ContainerBuilder
     * @throws SmartlingConfigException
     */
    public static function getContainer()
    {
        if (null === self::$containerInstance) {
            self::initContainer();
        }

        return self::$containerInstance;
    }
}