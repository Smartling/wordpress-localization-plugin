<?php

namespace Smartling;

use Smartling\Base\ExportedAPI;
use Smartling\Exception\SmartlingConfigException;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\LogContextMixinHelper;
use Smartling\MonologWrapper\Logger\LevelLogger;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Services\GlobalSettingsManager;
use Smartling\Vendor\Monolog\Handler\NullHandler;
use Smartling\Vendor\Symfony\Component\Config\FileLocator;
use Smartling\Vendor\Symfony\Component\DependencyInjection\ContainerBuilder;
use Smartling\Vendor\Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Smartling\WP\Controller\ConfigurationProfilesController;

trait DITrait
{
    private static ?ContainerBuilder $containerInstance = null;

    /**
     * Initializes DI Container from YAML config file
     * @throws SmartlingConfigException
     */
    protected static function initContainer(): void
    {
        $container = new ContainerBuilder();

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

        /**
         * Exposing reference to DI interface
         */
        do_action(ExportedAPI::ACTION_SMARTLING_BEFORE_INITIALIZE_EVENT, self::$containerInstance);

        // Init monolog wrapper when all the modifications to DI container
        // are done.
        MonologWrapper::init(self::$containerInstance);

        $logger = MonologWrapper::getLogger(__CLASS__);

        $host = false === gethostname() ? 'unknown' : gethostname();
        // context naming based on https://wiki.smartling.net/pages/viewpage.action?spaceKey=DEV&title=Log+service
        LogContextMixinHelper::addToContext('host', $host);
        LogContextMixinHelper::addToContext('httpHost', $_SERVER['HTTP_HOST']);
        LogContextMixinHelper::addToContext('moduleVersion', Bootstrap::$pluginVersion);
        LogContextMixinHelper::addToContext('phpVersion', PHP_VERSION);

        Bootstrap::$loggerInstance = $logger;
    }

    private static function injectLoggerCustomizations(ContainerBuilder $di): void
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

                        $di->register($defId, LevelLogger::class)
                            ->addArgument($item)
                            ->addArgument($level)
                            ->addArgument([$handler]);
                    }
                }
            }
        }
    }

    private static function handleLoggerConfiguration(): void
    {
        add_action(ExportedAPI::ACTION_SMARTLING_BEFORE_INITIALIZE_EVENT, static function (ContainerBuilder $di) {
            $di->setParameter('logger.filehandler.standard.filename', GlobalSettingsManager::getLogFileSpec());
            $di->setParameter('submission.pagesize', GlobalSettingsManager::getPageSize());
            if (1 === (int) GlobalSettingsManager::getDisableLogging()) {
                Bootstrap::disableLogging($di);
            }
        }, 8);

        add_action(ExportedAPI::ACTION_SMARTLING_BEFORE_INITIALIZE_EVENT, static function () {
            $file = self::getLogFileName();
            if (file_exists($file) && !is_writable($file)) {
                if (1 === (int) GlobalSettingsManager::getDisableLogging()) {
                    return;
                }
                add_action('admin_init', static function () {
                    $msg = [
                        '<strong>Warning!</strong>',
                        vsprintf('It is not possible to write runtime logs into a file <strong>%s</strong>.', [Bootstrap::getLogFileName()]),
                        'It\'s highly important to have a log file in case of troubleshooting issues with translations.',
                        vsprintf('Please review <a href="%s">logger configuration</a> and fix it.', [admin_url('admin.php?page=' . ConfigurationProfilesController::MENU_SLUG)]),
                    ];
                    DiagnosticsHelper::addDiagnosticsMessage(implode('<br/>', $msg));
                });
            }
        }, 9);
    }

    /**
     *  Disable all defined loggers.
     */
    private static function nullLog(ContainerBuilder $di): void
    {
        foreach ($di->getDefinitions() as $serviceId => $serviceDefinition) {
            if ($serviceDefinition->getClass() === LevelLogger::class) {
                $di->get($serviceId)->setHandlers([new NullHandler()]);
            }
        }
    }

    public static function disableLogging(ContainerBuilder $di): void
    {
        self::nullLog($di);
        add_action('admin_init', static function () {
            $msg = [
                '<strong>Warning!</strong>',
                'Logging is completely disabled. Previous log files are untouched.',
                'It\'s highly important to have a log file in case of troubleshooting issues with translations.',
            ];
            DiagnosticsHelper::addDiagnosticsMessage(implode('<br/>', $msg));
        });
    }

    protected function fromContainer(string $id, bool $isParam = false): mixed
    {
        $container = self::getContainer();

        return $isParam ? $container->getParameter($id) : $container->get($id);
    }

    /**
     * @throws SmartlingConfigException
     */
    public static function getContainer(): ContainerBuilder
    {
        if (null === self::$containerInstance) {
            self::initContainer();
        }

        return self::$containerInstance;
    }

    public static function getLogFileName(bool $withDate = true, bool $forceDefault = false): string
    {
        $container = static::getContainer();
        $pluginDir = $container->getParameter('plugin.dir');

        $filename = str_replace('%plugin.dir%', $pluginDir, $container->getParameter($forceDefault
            ? 'logger.filehandler.standard.filename.default'
            : 'logger.filehandler.standard.filename'));

        return $withDate
            ? sprintf('%s-%s', $filename, date('Y-m-d'))
            : sprintf('%s', $filename);
    }

    public static function getCurrentLogFileSize(): string
    {
        $logFile = static::getLogFileName();

        if (!file_exists($logFile) || !is_readable($logFile)) {
            return 'Log does not exist or is not readable';
        }

        $size = filesize($logFile);

        return static::prettyPrintSize($size);
    }

    public static function prettyPrintSize(float|int $size, int $stepForward = 750, int $divider = 1024, int $precision = 2): string
    {
        $scales = [
            'B' => 'B',
            'K' => 'kB',
            'M' => 'MB',
            'G' => 'GB',
            'T' => 'TB',
            'P' => 'PB',
            'E' => 'EB',
        ];

        $scale = reset($scales);

        while ($stepForward < $size) {
            $newSize = $size / $divider;
            $newScale = next($scales);

            if (false === $newScale) {
                break;
            }
            $size = $newSize;
            $scale = $newScale;
        }

        return sprintf('%s %s', round($size, $precision), $scale);
    }
}
