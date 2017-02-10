<?php

namespace Smartling\ContentTypes\ConfigParsers;

use Smartling\Helpers\StringHelper;

/**
 * Class PostTypeConfigParser
 * @package Smartling\ContentTypes\ConfigParsers
 */
class PostTypeConfigParser extends ConfigParserAbstract
{
    private function validateType($config)
    {
        if (is_string($config)) {
            $config = ['identifier' => $config];
        }

        $label = array_key_exists('label', $config) ? $config['label'] : '';

        $typeTemplate = [
            'identifier' => 'unknown',
            'label'      => '',
            'widget'     => [
                'visible' => false,
                'message' => vsprintf('No original %s found', [$label]),
            ],
            'visibility' => [
                'submissionBoard' => true,
                'bulkSubmit'      => true,
            ],
        ];

        $config = array_replace_recursive($typeTemplate, $config);

        if ('unknown' !== $config['identifier']) {
            $this->setIdentifier($config['identifier']);
        }
        if (!StringHelper::isNullOrEmpty($config['label'])) {
            $this->setLabel($config['label']);
        }
        $this->setWidgetVisible($config['widget']['visible']);
        $this->setWidgetMessage($config['widget']['message']);

        $this->setVisibility($config['visibility']);
    }

    /**
     * @return mixed
     */
    public function parse()
    {
        $rawConfig = $this->getRawConfig();
        if (array_key_exists('type', $rawConfig)) {
            $this->validateType($rawConfig['type']);
        }
    }
}