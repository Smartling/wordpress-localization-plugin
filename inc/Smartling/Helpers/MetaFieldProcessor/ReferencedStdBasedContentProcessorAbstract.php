<?php

namespace Smartling\Helpers\MetaFieldProcessor;

use Smartling\Exception\SmartlingWpDataIntegrityException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\Parsers\IntegerParser;
use Smartling\Helpers\TranslationHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

abstract class ReferencedStdBasedContentProcessorAbstract extends MetaFieldProcessorAbstract
{
    /**
     * @var ContentHelper
     */
    private $contentHelper;
    private SubmissionManager $submissionManager;

    /**
     * @return ContentHelper
     */
    public function getContentHelper()
    {
        return $this->contentHelper;
    }

    /**
     * @param ContentHelper $contentHelper
     */
    public function setContentHelper(ContentHelper $contentHelper)
    {
        $this->contentHelper = $contentHelper;
    }

    public function __construct(SubmissionManager $submissionManager, TranslationHelper $translationHelper, string $fieldRegexp)
    {
        parent::__construct();
        $this->setTranslationHelper($translationHelper);
        $this->setFieldRegexp($fieldRegexp);
        $this->submissionManager = $submissionManager;
    }

    /**
     * @throws SmartlingWpDataIntegrityException
     */
    abstract protected function detectRealContentType(int $blogId, int $contentId): string;

    /**
     * @param SubmissionEntity $submission
     * @param string           $fieldName
     * @param mixed            $value
     *
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

        $attachment = $this->submissionManager->findOne([
            SubmissionEntity::FIELD_CONTENT_TYPE => $this->detectRealContentType($submission->getSourceBlogId(), $value),
            SubmissionEntity::FIELD_SOURCE_BLOG_ID => $submission->getSourceBlogId(),
            SubmissionEntity::FIELD_SOURCE_ID => (int)$value,
            SubmissionEntity::FIELD_TARGET_BLOG_ID => $submission->getTargetBlogId(),
        ]);
        if ($attachment !== null) {
            return $attachment->getTargetId();
        }

        return $originalValue;
    }

    /**
     * @param SubmissionEntity $submission
     * @param string           $fieldName
     * @param mixed            $value
     * @param array            $collectedFields
     *
     * @return mixed or empty string (to skip translation)
     */
    public function processFieldPreTranslation(SubmissionEntity $submission, $fieldName, $value, array $collectedFields)
    {
        return $this->processFieldPostTranslation($submission, $fieldName, $value);
    }
}
