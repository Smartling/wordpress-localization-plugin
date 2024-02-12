<?php

namespace Smartling\ContentTypes;

use Smartling\Submissions\SubmissionEntity;

interface ContentTypeModifyingInterface extends ContentTypePluggableInterface
{
    public function removeUntranslatableFieldsForUpload(array $source, SubmissionEntity $submission): array;
}
