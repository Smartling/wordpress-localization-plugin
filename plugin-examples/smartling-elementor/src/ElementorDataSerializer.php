<?php namespace KPS3\Smartling\Elementor;

use Smartling\Helpers\Serializers\SerializerInterface;
use Smartling\MonologWrapper\MonologWrapper;

class ElementorDataSerializer implements SerializerInterface {
    public function getType(): string
    {
        return 'elementor_data';
    }

    /**
     * @param array $data
     */
    public function serialize($data): string
    {
        MonologWrapper::getLogger(static::class)->info('ElementorData serializer serialize');

        return json_encode($data, JSON_THROW_ON_ERROR);
    }

    /**
     * @param string $string
     */
    public function unserialize($string): array
    {
        MonologWrapper::getLogger(static::class)->info('ElementorData serializer unserialize');

        return json_decode($string, true, 512, JSON_THROW_ON_ERROR);
    }
}
