<?php

namespace Smartling\Base;

use Monolog\Formatter\LineFormatter;

/**
 * Class CustomLogLineFormatter
 *
 * @package Smartling\Base
 */
class CustomLogLineFormatter extends LineFormatter
{

    /**
     * Per-request unique key to identify all log records related to the request.
     *
     * @var string
     */
    private static $_requestId = null;

    /**
     * @inheritdoc
     */
    public function format(array $record)
    {
        $record['extra']['request_id'] = self::getRequestId();

        return parent::format($record);
    }

    /**
     * @return string
     */
    private static function getRequestId()
    {
        if (is_null(self::$_requestId)) {
            self::$_requestId = uniqid();
        }

        return self::$_requestId;
    }
}