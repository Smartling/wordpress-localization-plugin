<?php

namespace Smartling\Base;

use Smartling\Helpers\LogContextMixinHelper;
use Smartling\Vendor\GuzzleHttp\Client;
use Smartling\Vendor\Monolog\Handler\AbstractHandler;
use Smartling\Vendor\Monolog\Logger;

class SmartlingLogHandler extends AbstractHandler
{
    const DATETIME_FORMAT = 'Y-m-d H:i:s';

    /**
     * @var string
     */
    private $host;

    /**
     * @var string
     */
    private $format;

    /**
     * @var int
     */
    private $timeout;

    /**
     * {@inheritdoc}
     */
    public function __construct($host, $timeOut = 15, $format = self::DATETIME_FORMAT, $level = Logger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->host = $host;
        $this->format = $format;
        $this->timeout = $timeOut;
    }

    /**
     * {@inheritdoc}
     */
    public function handleBatch(array $records)
    {
        $resp = null;
        try {
            $client = new Client(['defaults' => ['exceptions' => false]]);
            $data = $this->formatRecords($records);
            $response = $client->request(
                'POST',
                $this->host,
                [
                    'json'    => $data,
                    'timeout' => $this->timeout,
                ]
            );
        } catch (\Exception $e) {
        }
    }

    /**
     * @param array  $records
     * @param string $format
     *
     * @return array
     */
    private function formatRecords(array & $records, $format = self::DATETIME_FORMAT)
    {
        $_records = [];
        foreach ($records as & $record) {
            $context = $record['context'];

            if (class_exists('\Smartling\Helpers\LogContextMixinHelper')) {
                $context = array_merge($context, LogContextMixinHelper::getContextMixin());
            }

            $context['loggerChannel'] = $record['channel'];

            $_records[] = [
                'level_name' => $record['level_name'],
                'channel'    => 'wordpress',
                'datetime'   => $record['datetime']->format($format),
                'context'    => $context,
                'message'    => $record['message'],
            ];
        }

        return ['records' => $_records];
    }

    /**
     * {@inheritdoc}
     */
    public function handle(array $record)
    {
        // Pass records to other handlers.
        return false === $this->bubble;
    }
}