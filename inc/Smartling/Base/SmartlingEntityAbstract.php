<?php

namespace Smartling\Base;

use Smartling\MonologWrapper\MonologWrapper;
use Smartling\SmartlingTableDefinitionInterface;
use Smartling\Vendor\Psr\Log\LoggerInterface;

abstract class SmartlingEntityAbstract implements SmartlingTableDefinitionInterface
{
    public const string DB_TYPE_INT_MODIFIER_AUTOINCREMENT = 'AUTO_INCREMENT';
    public const string DB_TYPE_DEFAULT_ZERO = 'DEFAULT \'0\'';
    public const string DB_TYPE_DEFAULT_EMPTYSTRING = 'DEFAULT \'\'';

    public const string DB_TYPE_U_BIGINT = 'INT(20) UNSIGNED NOT NULL'; // BIGINT alias of INT(20)
    public const string DB_TYPE_DATETIME = 'DATETIME NOT NULL DEFAULT \'0000-00-00 00:00:00\'';
    public const string DB_TYPE_STRING_STANDARD = 'VARCHAR(255) NOT NULL';
    public const string DB_TYPE_STRING_64 = 'VARCHAR(64) NOT NULL';
    public const string DB_TYPE_STRING_SMALL = 'VARCHAR(16) NOT NULL';
    public const string DB_TYPE_UINT_SWITCH = 'INT(1) UNSIGNED NOT NULL DEFAULT \'0\'';
    public const string DB_TYPE_UINT_SWITCH_ON = 'INT(1) UNSIGNED NOT NULL DEFAULT \'1\'';

    public const string DB_TYPE_STRING_TEXT = 'TEXT NOT NULL';

    public const string DB_TYPE_HASH_MD5 = 'CHAR(32) NOT NULL';
    /**
     * @var array
     */
    protected $stateFields = [];

    /**
     * @var bool
     */
    protected $initialValuesFixed = false;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var array
     */
    protected $initialFields = [];

    protected static function under_score2camelCase($string)
    {
        $converted = '';
        foreach (explode('_', $string) as $part) {
            $converted .= ucfirst($part);
        }

        return $converted;
    }

    /**
     * Magic wrapper for fields
     * may be used as virtual setter, e.g.:
     *      $object->content_type = $value
     * instead of
     *      $object->setContentType($value)
     *
     * @param string $key
     * @param mixed  $value
     */
    public function __set($key, $value)
    {
        if (array_key_exists($key, $this->stateFields)) {
            $setter = 'set' . static::under_score2camelCase($key);
            if (!$this->initialValuesFixed && !array_key_exists($key, $this->initialFields)) {
                $this->initialFields[$key] = $value;
            }
            $this->$setter($value);
        }
    }

    /**
     * Magic wrapper for fields
     * may be used as virtual setter, e.g.:
     *      $value = $object->content_type
     * instead of
     *      $value = $object->getContentType()
     *
     * @param string $key
     */
    public function __get($key)
    {
        if (array_key_exists($key, $this->stateFields)) {
            $getter = 'get' . static::under_score2camelCase($key);

            return $this->$getter();
        }
    }

    /**
     * @return array
     */
    protected function getVirtualFields()
    {
        return [];
    }

    /**
     * @param bool $addVirtualColumns
     *
     * @return array
     */
    public function toArray($addVirtualColumns = true)
    {
        $arr = $this->stateFields;
        if (true === $addVirtualColumns) {
            $arr = array_merge($arr, $this->getVirtualFields());
        }

        return $arr;
    }


    /**
     * @return array
     */
    public function getChangedFields()
    {
        $curFields = $this->stateFields;
        $initialFields = $this->initialFields;
        $output = [];

        $fieldNames = array_keys($curFields);
        foreach ($fieldNames as $fieldName) {
            if (
                !array_key_exists($fieldName, $initialFields) ||
                (((string)$initialFields[$fieldName]) !== ((string)$curFields[$fieldName]))
            ) {
                $output[$fieldName] = (string)$curFields[$fieldName];
            }
        }

        return $output;
    }

    /**
     * Converts associative array to entity
     * array keys must match field names;
     */
    public static function fromArray(array $array, LoggerInterface $logger)
    {
        $obj = static::getInstance();

        foreach ($array as $field => $value) {
            $obj->$field = $value;
        }

        // Fix initial values to detect what fields were changed.
        $obj->fixInitialValues();

        return $obj;
    }


    /**
     * Constructor
     */
    public function __construct()
    {
        $this->logger = MonologWrapper::getLogger(get_called_class());

        $this->stateFields = array_flip(array_keys(static::getFieldDefinitions()));

        foreach ($this->stateFields as & $value) {
            $value = null;
        }
    }

    public function fixInitialValues()
    {
        $this->initialValuesFixed = true;
    }

    /**
     * @param $fieldName
     *
     * @return string
     */
    static public function getFieldLabel($fieldName)
    {
        $labels = static::getFieldLabels();

        return array_key_exists($fieldName, $labels) ? $labels[$fieldName] : $fieldName;
    }
}
