<?php
namespace Smartling\DbAl;

use Psr\Log\LoggerInterface;
use Smartling\Exception\SmartlingDirectRunRuntimeException;
use Smartling\Helpers\SiteHelper;

abstract class MultilangPluginAbstract implements MultilangPluginProxy
{
    protected $helper = null;

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