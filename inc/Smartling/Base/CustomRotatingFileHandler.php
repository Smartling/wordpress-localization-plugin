<?php

namespace Smartling\Base;

use Smartling\Bootstrap;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Vendor\Monolog\Handler\RotatingFileHandler;

/**
 * Class CustomRotatingFileHandler
 * @package Smartling\Base
 */
class CustomRotatingFileHandler extends RotatingFileHandler
{
    /**
     * @var int
     */
    private $messageDisplayed = 0;

    /**
     * {@inheritdoc}
     */
    protected function write(array $record)
    {
        try {
            parent::write($record);
        } catch (\Exception $e) {
            if (0 === $this->messageDisplayed) {
                add_action('admin_init', function () use ($e) {

                    $msg = [
                        '<strong>Warning!</strong>',
                        vsprintf('An error occurred while writing a log file: <strong>%s</strong>.', [Bootstrap::getLogFileName()]),
                        vsprintf('Message: %s', [$e->getMessage()]),
                        'It\'s highly important to have a log file in case of troubleshooting issues with translations.',
                        vsprintf('Please review <a href="%s">logger configuration</a> and fix it.', [admin_url('admin.php?page=smartling_configuration_profile_list')]),
                    ];
                    DiagnosticsHelper::addDiagnosticsMessage(implode('<br/>', $msg));
                }, 99);
                $this->messageDisplayed = 1;
            }
        }
    }
}