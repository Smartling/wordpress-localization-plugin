<?php

namespace Smartling\ContentTypes;

interface ContentTypeModifyingInterface extends ContentTypePluggableInterface
{
    public function alterContentFields(array $source): array;
}
