<?php

namespace Smartling\Services;

use Smartling\Base\ExportedAPI;
use Smartling\Helpers\EventParameters\BeforeSerializeContentEventParameters;
use Smartling\WP\WPHookInterface;

class InvalidCharacterCleaner implements WPHookInterface
{

    const ILLEGAL_CHAR_REGEXP = '#[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+#us';

    /**
     * Registers wp hook handlers. Invoked by wordpress.
     * @return void
     */
    public function register(): void
    {
        add_action(ExportedAPI::EVENT_SMARTLING_BEFORE_SERIALIZE_CONTENT, [$this, 'cleanStrings']);
    }

    /**
     * @param BeforeSerializeContentEventParameters $params
     *
     * @return BeforeSerializeContentEventParameters
     */
    public function cleanStrings(BeforeSerializeContentEventParameters $params)
    {
        $fields = &$params->getPreparedFields();

        $this->processArray($fields);

        return $params;
    }

    /**
     * @param array $array
     */
    public function processArray(array & $array)
    {
        foreach ($array as & $item) {
            if (is_array($item)) {
                $this->processArray($item);
            } else {
                $item = preg_replace(self::ILLEGAL_CHAR_REGEXP, '', $item);
            }
        }
    }
}