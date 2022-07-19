<?php

namespace Smartling\ContentTypes;

interface ContentTypeModifyingInterface extends ContentTypePluggableInterface
{
    public function alterContentFieldsForUpload(array $source): array;
}
