<?php
namespace Smartling\Helpers\MetaFieldProcessor;

use Smartling\Submissions\SubmissionEntity;

class NavigationMenuItemProcessor extends ReferencedContentProcessor
{
    public function processFieldPreTranslation(SubmissionEntity $submission, $fieldName, $value, array $collectedFields, string $contentType = null): mixed
    {
        try {
            $originalMetadata = $this->contentHelper->readSourceMetadata($submission);
            if (array_key_exists('_menu_item_type', $originalMetadata) &&
                in_array($originalMetadata['_menu_item_type'], ['taxonomy', 'post_type'], true)
            ) {
                $contentType = $originalMetadata['_menu_item_object'] ?? null;
                if ($contentType !== null) {
                    return parent::processFieldPreTranslation($submission, $fieldName, $value, $collectedFields, $contentType);
                }
            }
        } catch (\Exception $e) {
            $this->getLogger()->debug("An exception occurred while processing field '$fieldName'='$value' of submission {$submission->getId()}: {$e->getMessage()}");
        }

        return $value;
    }
}
