<?php

namespace Smartling\Helpers\MetaFieldProcessor;

use Psr\Log\LoggerInterface;
use Smartling\Exception\SmartlingDataReadException;
use Smartling\Exception\SmartlingDbException;
use Smartling\Exception\SmartlingWpDataIntegrityException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\Parsers\IntegerParser;
use Smartling\Helpers\TranslationHelper;
use Smartling\Submissions\SubmissionEntity;

/**
 * Class ReferencedPostBasedContentProcessor
 * @package Smartling\Helpers\MetaFieldProcessor
 */
class ReferencedPostBasedContentProcessor extends MetaFieldProcessorAbstract
{
    /**
     * @var ContentHelper
     */
    private $contentHelper;

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
    public function setContentHelper($contentHelper)
    {
        $this->contentHelper = $contentHelper;
    }

    /**
     * ReferencedPostBasedContentProcessor constructor.
     *
     * @param LoggerInterface   $logger
     * @param TranslationHelper $translationHelper
     * @param string            $fieldRegexp
     */
    public function __construct(LoggerInterface $logger, TranslationHelper $translationHelper, $fieldRegexp)
    {
        $this->setLogger($logger);
        $this->setTranslationHelper($translationHelper);
        $this->setFieldRegexp($fieldRegexp);
    }

    /**
     * @param $blogId
     * @param $postId
     *
     * @return string
     * @throws SmartlingWpDataIntegrityException
     */
    private function detectRealContentType($blogId, $postId)
    {
        try {
            $this->getContentHelper()->ensureBlog($blogId);
            $post = get_post($postId);
            $this->getContentHelper()->ensureRestoredBlogId();

            if ($post instanceof \WP_Post) {
                return $post->post_type;
            } else {
                $message = vsprintf(
                    'The post-based content with id=\'%s\' not found in blog=\'%s\'',
                    [
                        $postId,
                        $blogId,
                    ]
                );
                throw new SmartlingDbException($message);
            }
        } catch (\Exception $e) {
            $message = vsprintf(
                'Error happened while detecting the real content type for content id=\'%s\' blog = \'%s\'',
                [
                    $postId,
                    $blogId,
                ]
            );
            throw new SmartlingWpDataIntegrityException($message, 0, $e);
        }
    }

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

        try {
            $this->getLogger()->debug(
                vsprintf(
                    'Sending for translation referenced content id = \'%s\' related to submission = \'%s\'.',
                    [$value, $submission->getId()]
                )
            );

            $contentType = null;
            try {
                $contentType = $this->detectRealContentType($submission->getSourceBlogId(), $value);
            } catch (SmartlingWpDataIntegrityException $e) {
                $this->getLogger()->debug(
                    vsprintf(
                        'Couldn\'t identify content type for id=\'%s\', blog=\'%s\'. Keeping existing value \'%s\'.',
                        [$value, $submission->getSourceBlogId(), $value]
                    )
                );

                return $value;
            }

            // trying to detect
            $attSubmission = $this->getTranslationHelper()->tryPrepareRelatedContent(
                $contentType,
                $submission->getSourceBlogId(),
                $value,
                $submission->getTargetBlogId()
            );

            return $attSubmission->getTargetId();
        } catch (SmartlingDataReadException $e) {
            $message = vsprintf(
                'An error happened while processing referenced content with original value=%s. Keeping original value.',
                [
                    var_export($originalValue, true),
                ]
            );
            $this->getLogger()->error($message);


        } catch (\Exception $e) {
            $message = vsprintf(
                'An exception occurred while sending related item=%s, submission=%s for translation. Message: %s',
                [
                    var_export($originalValue, true),
                    $submission->getId(),
                    $e->getMessage(),
                ]
            );
            $this->getLogger()->error($message);

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