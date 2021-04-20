<?php
namespace Smartling\Helpers\MetaFieldProcessor;

use Smartling\ContentTypes\ContentTypeNavigationMenuItem;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\Parsers\IntegerParser;
use Smartling\Submissions\SubmissionEntity;

class NavigationMenuItemProcessor extends ReferencedContentProcessor
{
    /**
     * @param SubmissionEntity $submission
     * @param string $fieldName
     * @param mixed $value
     * @return mixed
     */
    public function processFieldPostTranslation(SubmissionEntity $submission, $fieldName, $value)
    {
        $originalValue = $value;

        if (is_array($value)) {
            $value = ArrayHelper::first($value);
        }

        if (!IntegerParser::tryParseString($value, $value)) {
            $message = vsprintf(
                'Got bad reference number for submission id=%s metadata field=\'%s\' with value=\'%s\', expected integer > 0. Skipping.',
                [$submission->getId(), $fieldName, var_export($originalValue, true),]
            );
            $this->getLogger()->warning($message);

            return $originalValue;
        }

        if (0 === $value) {
            return $value;
        }

        try {
            $this->getLogger()->debug(
                vsprintf(
                    'Sending for translation referenced content id = \'%s\' related to submission = \'%s\'.',
                    [$value, $submission->getId()]
                )
            );

            $originalMetadata = $this->getContentHelper()->readSourceMetadata($submission);

            if (array_key_exists('_menu_item_type', $originalMetadata) &&
                in_array($originalMetadata['_menu_item_type'], ['taxonomy', 'post_type'], true)
            ) {
                $relatedContentType = $originalMetadata['_menu_item_object'];
                $sourceBlogId = $submission->getSourceBlogId();
                $targetBlogId = $submission->getTargetBlogId();
                if ($this->getTranslationHelper()->isRelatedSubmissionCreationNeeded($relatedContentType, $sourceBlogId, $value, $targetBlogId)) {
                    $this->getLogger()->debug(sprintf("Sending for translation object = '$relatedContentType' id = '$value' related to '%s' related to submission = '{$submission->getId()}'.", ContentTypeNavigationMenuItem::WP_CONTENT_TYPE));

                    return $this->getTranslationHelper()->tryPrepareRelatedContent(
                        $relatedContentType,
                        $sourceBlogId,
                        $value,
                        $targetBlogId,
                        $submission->getJobInfoWithBatchUid(),
                        (1 === $submission->getIsCloned())
                    )->getTargetId();
                }

                $this->getLogger()->debug("Skip sending object = '$relatedContentType' id = '$value' due to manual relations handling");
            }
        } catch (\Exception $e) {
            $this->getLogger()->debug("An exception occurred while processing field '$fieldName'='$value' of submission {$submission->getId()}: {$e->getMessage()}");
        }

        return 0;
    }
}
