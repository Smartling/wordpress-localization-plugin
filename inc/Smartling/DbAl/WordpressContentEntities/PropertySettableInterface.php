<?php

namespace Smartling\DbAl\WordpressContentEntities;

interface PropertySettableInterface
{
    public function setProperty(string $name, string $value): void;
}
