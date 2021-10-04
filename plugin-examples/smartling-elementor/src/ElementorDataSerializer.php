<?php namespace KPS3\Smartling\Elementor;

use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\Serializers\SerializerInterface;

class ElementorDataSerializer implements SerializerInterface {

    use LoggerSafeTrait;

    public function getType(): string
    {
        return 'elementor_data';
    }

    /**
     * @param array $data
     */
    public function serialize($data): string
    {
        $this->getLogger()->info('ElementorData serializer serialize');

        return json_encode($data, JSON_THROW_ON_ERROR);
    }

    /**
     * @param string $string
     */
    public function unserialize($string): array
    {
        $this->getLogger()->info('ElementorData serializer unserialize');

        return json_decode($string, true, 512, JSON_THROW_ON_ERROR);
    }
}
