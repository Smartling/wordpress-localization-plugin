<?php
namespace Smartling\DbAl;

use Psr\Log\LoggerInterface;
use Smartling\Exception\SmartlingDirectRunRuntimeException;
use Smartling\Helpers\SiteHelper;
use Smartling\MonologWrapper\MonologWrapper;

abstract class MultilingualPressAbstract implements LocalizationPluginProxyInterface
{
    public function addHooks(): void
    {
        // No hooks by default
    }

    /**
     * Fallback for direct run if Wordpress functionality is not reachable
     *
     * @throws SmartlingDirectRunRuntimeException
     */
    protected function directRunFallback(string $message): void
    {
        throw new SmartlingDirectRunRuntimeException($message);
    }
}
