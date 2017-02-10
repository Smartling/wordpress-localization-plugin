<?php

namespace Smartling\ContentTypes\ConfigParsers;

use Smartling\Helpers\StringHelper;

/**
 * Class CustomPostTypeConfigParser
 * @package Smartling\ContentTypes
 */
class TermTypeConfigParser extends ConfigParserAbstract
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
        if (array_key_exists('taxonomy', $rawConfig)) {
            $this->validateType($rawConfig['taxonomy']);
        }
    }
}