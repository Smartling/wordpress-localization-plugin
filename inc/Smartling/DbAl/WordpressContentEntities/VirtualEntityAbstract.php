<?php

namespace Smartling\DbAl\WordpressContentEntities;

abstract class VirtualEntityAbstract extends EntityAbstract
{
    protected array $fields;

    public function getContentTypeProperty(): string
    {
        return '';
    }

    protected function getFieldNameByMethodName($method): string
    {
        $way = substr($method, 0, 3);
        $possibleField = lcfirst(substr($method, 3));
        if (in_array($way, ['set', 'get']) && in_array($possibleField, $this->fields, true)) {
            return $possibleField;
        }

        $message = vsprintf('Method %s not found in %s', [$method, __CLASS__]);
        $this->getLogger()->error($message);
        throw new \BadMethodCallException($message);
    }
}
