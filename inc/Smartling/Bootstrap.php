<?php

namespace Smartling;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class Bootstrap {

    /**
     * @var ContainerBuilder $container
     */
    private static $_container = null;

    public static function getContainer()
    {
        if (is_null(self::$_container)) {
            self::$_container = new ContainerBuilder();
        }

        return self::$_container;
    }

    public static function __bootstrap()
    {
        self::initLogger();
    }

    protected static function initLogger()
    {
        $container = self::getContainer();
        /**
         * @var ContainerBuilder $container
         */
        $container->setParameter('logger.channel', SMARTLING_DEFAULT_LOG_CHANNEL);

        $container
            ->register('logger', 'Monolog\Logger')
            ->addArgument('%logger.channel%')
            ->addMethodCall('pushHandler',
                array(
                    new StreamHandler(SMARTLING_DEFAULT_LOGFILE, SMARTLING_DEFAULT_LOGLEVEL)
                )
            );

        $container->get('logger')->addInfo('Logger initialized.');


    }
}