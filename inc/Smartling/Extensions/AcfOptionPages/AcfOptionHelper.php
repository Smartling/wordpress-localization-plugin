<?php

namespace Smartling\Extensions\AcfOptionPages;
use Smartling\Helpers\OptionHelper;
use Smartling\Helpers\StringHelper;

/**
 * Class AcfOptionHelper
 */
class AcfOptionHelper
{
    const ACF_OPTION_KEY_MAP = 'smartling_acf_option_key_map';

    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $value;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param string $value
     */
    public function setValue($value)
    {
        $this->value = $value;
    }

    /**
     * Reads the Acf key map array
     * @return array
     */
    public static function getMap()
    {
        return OptionHelper::get(self::ACF_OPTION_KEY_MAP, []);
    }

    /**
     * Writes the Acf key map array
     *
     * @param array $map
     */
    private function setMap(array $map = [])
    {
        OptionHelper::set(self::ACF_OPTION_KEY_MAP, $map);
    }

    /**
     * AcfOptionHelper constructor.
     *
     * @param string $acfOptionName
     */
    public function __construct($acfOptionName)
    {
        $this->setName($acfOptionName);
        $this->read();
    }

    public function tryGetOptionId()
    {
        $map = self::getMap();

        if (array_key_exists($this->getName(), $map)) {
            return $map[$this->getName()];
        } else {
            $newId = 0 === count($map) ? 1 : max(array_values($map)) + 1;
            $map[$this->getName()] = $newId;
            $this->setMap($map);

            return $this->tryGetOptionId();
        }
    }

    /**
     * @return void
     */
    public function read()
    {
        $value = OptionHelper::get($this->getName(), false);
        if (false !== $value) {
            $this->setValue($value);
            $id = null === $this->getPk() ? $this->tryGetOptionId() : $this->getPk();
            $this->setId($id);
        }
    }

    /**
     * @return int|null
     */
    public function getPk()
    {
        $map = self::getMap();

        return array_key_exists($this->getName(), $map) ? (int)$map[$this->getName()] : null;
    }

    /**
     * @return void
     */
    public function write()
    {
        if (!StringHelper::isNullOrEmpty($this->getName())) {
            if (null === $this->getPk()) {
                $this->setId($this->tryGetOptionId());
            }

            OptionHelper::set($this->getName(), $this->getValue());
        }
    }

    /**
     * @param array $state
     *
     * @return AcfOptionHelper
     */
    public static function fromArray(array $state)
    {
        $instance = new self($state['name']);
        $instance->setValue($state['value']);

        if (array_key_exists('id', $state) && null !== $state['id'] && 0 < (int)$state['id']) {
            $instance->setId((int)$state['id']);
        }

        return $instance;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'id'    => $this->getId(),
            'name'  => $this->getName(),
            'value' => $this->getValue(),
        ];
    }

    /**
     * @param string $acfOptionName
     *
     * @return AcfOptionHelper
     */
    public static function getOption($acfOptionName)
    {
        $optionInstance = new self($acfOptionName);
        if (null === $optionInstance->getPk()) {
            $optionInstance = new self($acfOptionName);
        }

        return $optionInstance;
    }
}
