<?php
namespace Smartling\DbAl;

use Psr\Log\LoggerInterface;
use Smartling\Exception\SmartlingDirectRunRuntimeException;
use Smartling\Helpers\SiteHelper;

/**
 * Class MultilingualPluginAbstract
 * @package Smartling\DbAl
 */
abstract class MultilingualPluginAbstract implements MultilingualPluginProxyInterface
{
    /**
     * @var SiteHelper
     */
    protected $helper = null;

    /**
     * @var LoggerInterface
     */
    private $logger = null;

    /**
     * @inheritdoc
     */
    public function __construct(LoggerInterface $logger, SiteHelper $helper, array $ml_plugin_statuses)
    {
        $this->logger = $logger;
        $this->helper = $helper;
    }

    /**
     * Fallback for direct run if Wordpress functionality is not reachable
     * @throws SmartlingDirectRunRuntimeException
     */
    protected function directFunFallback($message)
    {
        $this->logger->error($message);

        throw new SmartlingDirectRunRuntimeException($message);
    }

}