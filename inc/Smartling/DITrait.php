<?php

namespace Smartling;

use Monolog\Handler\NullHandler;
use Smartling\Base\ExportedAPI;
use Smartling\Exception\SmartlingConfigException;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\LogContextMixinHelper;
use Smartling\Helpers\SimpleStorageHelper;
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

        self::handleLoggerConfiguration();

        /**
         * Exposing reference to DI interface
         */
        do_action(ExportedAPI::ACTION_SMARTLING_BEFORE_INITIALIZE_EVENT, self::$containerInstance);

        $logger = $container->get('logger');
        $logger->pushProcessor(function ($record) {
            $record['context'] =
                array_merge(
                    $record['context'],
                    LogContextMixinHelper::getContextMixin()
                );

            return $record;
        });

        $host = false === gethostname() ? 'unknown' : gethostname();
        LogContextMixinHelper::addToContext('host', $host);
        LogContextMixinHelper::addToContext('http_host', $_SERVER['HTTP_HOST']);
        LogContextMixinHelper::addToContext('version', self::$pluginVersion);

        self::$loggerInstance = $logger;
    }

    private static function handleLoggerConfiguration()
    {
        add_action(ExportedAPI::ACTION_SMARTLING_BEFORE_INITIALIZE_EVENT, function (ContainerBuilder $di) {

            $defaultLogFileName = Bootstrap::getLogFileName(false, true);
            $storedFile = SimpleStorageHelper::get(self::SMARTLING_CUSTOM_LOG_FILE, false);
            $logFileName = false !== $storedFile ? $storedFile : $defaultLogFileName;

            $storedPageSize = SimpleStorageHelper::get(self::SMARTLING_CUSTOM_PAGE_SIZE, false);
            $pageSize = false !== $storedPageSize ? $storedPageSize : self::getPageSize(true);

            $di->setParameter('logger.filehandler.standard.filename', $logFileName);
            $di->setParameter('submission.pagesize', $pageSize);


            $val = (int)SimpleStorageHelper::get(self::DISABLE_LOGGING, 0);
            if (1 === $val) {
                Bootstrap::disableLogging($di);
            }
        }, 8);

        add_action(ExportedAPI::ACTION_SMARTLING_BEFORE_INITIALIZE_EVENT, function (ContainerBuilder $di) {
            $file = self::getLogFileName();
            if (file_exists($file) && !is_writable($file)) {
                if (1 === (int)SimpleStorageHelper::get(self::DISABLE_LOGGING, 0)) {
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
        $logger = $di->get('logger');
        $logger->setHandlers([new NullHandler()]);
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