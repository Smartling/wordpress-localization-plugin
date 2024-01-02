<?php

namespace Smartling\ContentTypes;

interface ContentTypeModifyingInterface extends ContentTypePluggableInterface
{
    public function removeUntranslatableFieldsForUpload(array $source): array;
}
