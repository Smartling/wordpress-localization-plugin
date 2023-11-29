<?php

namespace Smartling\Base;

use Smartling\Helpers\LogContextMixinHelper;
use Smartling\Vendor\GuzzleHttp\Client;
use Smartling\Vendor\Monolog\Handler\AbstractHandler;
use Smartling\Vendor\Monolog\Logger;

class SmartlingLogHandler extends AbstractHandler {

    public function __construct(
        private string $host,
        private string $format,
        private int $timeOut = 15,
        $level = Logger::DEBUG,
        $bubble = true,
    ) {
        parent::__construct($level, $bubble);
    }

    public function handleBatch(array $records): void
    {
        try {
            $data = $this->formatRecords($records);
            (new Client(['defaults' => ['exceptions' => false]]))->request(
                'POST',
                $this->host,
                [
                    'json' => $data,
                    'timeout' => $this->timeOut,
                ]
            );
        } catch (\Exception) {
        }
    }

    private function formatRecords(array &$records): array
    {
        $_records = [];
        foreach ($records as $record) {
            $context = $record['context'];

            if (class_exists(LogContextMixinHelper::class)) {
                $context = array_merge($context, LogContextMixinHelper::getContextMixin());
                $record['message'] .= LogContextMixinHelper::getStringContext();
            }

            $context['loggerChannel'] = $record['channel'];

            $_records[] = [
                'level_name' => $record['level_name'],
                'channel' => 'wordpress',
                'datetime' => $record['datetime']->format($this->format),
                'context' => $context,
                'message' => $record['message'],
            ];
        }

        return ['records' => $_records];
    }

    public function handle(array $record): bool
    {
        // Pass records to other handlers.
        return false === $this->bubble;
    }
}
