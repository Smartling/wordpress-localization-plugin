<?php

namespace Smartling\Helpers;

use Smartling\Helpers\QueryBuilder\Condition\Condition;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBlock;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBuilder;
use Smartling\Helpers\QueryBuilder\QueryBuilder;
use Smartling\Submissions\SubmissionEntity;

/**
 * Class AbsoluteLinkedAttachmentCoreHelper
 * @package Smartling\Helpers
 */
class AbsoluteLinkedAttachmentCoreHelper extends RelativeLinkedAttachmentCoreHelper
{
    const PATTERN_LINK_GENERAL = '<a[^>]+>';

    /**
     * @param string $url
     * @return bool
     */
    private function isAbsoluteUrl($url)
    {
        $parsedUrl = parse_url($url);

        return false !== $parsedUrl
               && array_key_exists('scheme', $parsedUrl)
               && !StringHelper::isNullOrEmpty($parsedUrl['scheme']);
    }

    /**
     * @param string $url
     * @return bool
     */
    private function isUrlThumbnail($url)
    {
        return $this->fileLooksLikeThumbnail(pathinfo($this->urlToFile($url))['filename']);
    }

    /**
     * Searches for $url in `guid` field in `posts` table
     *
     * @param string $url
     *
     * @return int|false
     */
    private function lookForDirectGuidEntry($url)
    {
        $conditionBlock = ConditionBlock::getConditionBlock();

        $condition = Condition::getCondition(ConditionBuilder::CONDITION_SIGN_LIKE, 'guid', ['%' . $url]);

        $conditionBlock->addCondition($condition);

        $query = QueryBuilder::buildSelectQuery(
            RawDbQueryHelper::getTableName('posts'),
            ['ID' => 'id'],
            $conditionBlock
        );

        return $this->returnId($query);
    }

    /**
     * @param string           $originalUrl
     * @param SubmissionEntity $submission
     *
     * @return string
     */
    private function generateTranslatedUrl($originalUrl, SubmissionEntity $submission)
    {
        $result = $this->getCore()->getAttachmentAbsolutePathBySubmission($submission);

        if ($this->isUrlThumbnail($originalUrl)) {

            $originalPathInfo = pathinfo($this->urlToFile($originalUrl));

            $translatedPathInfo = pathinfo($this->urlToFile($result));

            $translatedPathInfo['filename'] = $originalPathInfo['filename'];

            $translatedFile = vsprintf('%s/%s.%s', [
                $translatedPathInfo['dirname'],
                $translatedPathInfo['filename'],
                $translatedPathInfo['extension'],
            ]);

            $result = $this->fileToUrl($translatedFile);
        }

        if (!$this->isAbsoluteUrl($originalUrl)) {
            $result = parse_url($result, PHP_URL_PATH);
        }

        return $result;
    }

    /**
     * @param PairReplacerHelper $replacer
     * @param string $path
     * @return PairReplacerHelper
     */
    private function processContent(PairReplacerHelper $replacer, $path)
    {
        $result = $replacer;
        if (false !== $path && $this->isAbsoluteUrl($path)) {
            $attachmentId = $this->getAttachmentId($path);
            if (false !== $attachmentId) {
                $submission = $this->getParams()->getSubmission();
                $sourceBlogId = $submission->getSourceBlogId();
                $targetBlogId = $submission->getTargetBlogId();
                if ($this->getCore()->getTranslationHelper()->isRelatedSubmissionCreationNeeded('attachment', $sourceBlogId, (int)$attachmentId, $targetBlogId)) {
                    $attachmentSubmission = $this->getCore()->sendAttachmentForTranslation($sourceBlogId, $targetBlogId, $attachmentId, $submission->getBatchUid(), $submission->getIsCloned());

                    $newPath = $this->generateTranslatedUrl($path, $attachmentSubmission);
                    $replacer->addReplacementPair(new ReplacementPair($path, $newPath));
                    $this->getLogger()->debug(sprintf("%s has replaced URL from '%s' to '%s'", __CLASS__, $path, $newPath));
                } else {
                    $this->getLogger()->debug("Skipping attachment id $attachmentId due to manual relations handling");
                }
            } else {
                $this->getLogger()->info(vsprintf('No \'attachment\' found for url=%s', [$path]));
            }
        }

        return $result;
    }

    /**
     * @param string $url
     * @param int $blogId
     * @return bool|int
     */
    public function getAttachmentIdByURL($url, $blogId)
    {
        $result = false;
        if (false !== $url && $this->isAbsoluteUrl($url)) {
            $result = $this->getAttachmentIdByBlogId($url, $blogId);
        }
        return $result;
    }

    /**
     * @param string $string
     * @param int    $blogId
     * @return array
     */
    public function getImagesIdsFromString($string, $blogId)
    {
        $ids = [];

        $matches = [];
        if (0 < preg_match_all(StringHelper::buildPattern(static::PATTERN_IMAGE_GENERAL), $string, $matches)) {
            foreach ($matches[0] as $match) {
                $path = $this->getAttributeFromTag($match, 'img', 'src');
                if (false !== $attachmentId = $this->getAttachmentIdByURL($path, $blogId)) {
                    $ids[] = $attachmentId;
                }
            }
        }
        if (0 < preg_match_all(StringHelper::buildPattern(self::PATTERN_LINK_GENERAL), $string, $matches)) {
            foreach ($matches[0] as $match) {
                $path = $this->getAttributeFromTag($match, 'a', 'href');
                if (false !== $attachmentId = $this->getAttachmentIdByURL($path, $blogId)) {
                    $ids[] = $attachmentId;
                }
            }
        }

        return $ids;
    }

    /**
     * Recursively processes all found strings
     *
     * @param $stringValue
     */
    protected function processString(&$stringValue)
    {
        $replacer = new PairReplacerHelper();
        if (is_array($stringValue)) {
            foreach ($stringValue as $item => &$value) {
                $this->processString($value);
            }
            unset($value);
        } else {
            $matches = [];
            if (0 < preg_match_all(StringHelper::buildPattern(static::PATTERN_IMAGE_GENERAL), $stringValue, $matches)) {
                foreach ($matches[0] as $match) {
                    $path = $this->getAttributeFromTag($match, 'img', 'src');
                    $replacer = $this->processContent($replacer, $path);
                }
            }
            if (0 < preg_match_all(StringHelper::buildPattern(self::PATTERN_LINK_GENERAL), $stringValue, $matches)) {
                foreach ($matches[0] as $match) {
                    $path = $this->getAttributeFromTag($match, 'a', 'href');
                    $replacer = $this->processContent($replacer, $path);
                }
            }
        }
        $stringValue = $replacer->processString($stringValue);
    }

    /**
     * Converts wordpress attachment URL to file location (for source attachment)
     *
     * @param string $url
     *
     * @return string
     */
    private function urlToFile($url)
    {
        return $this->urlToFileByBlogId($url, $this->getParams()->getSubmission()->getSourceBlogId());
    }

    private function urlToFileByBlogId($url, $blogId)
    {
        $parsedUrlPath = parse_url($url, PHP_URL_PATH);
        $sourceUploadInfo = $this->getCore()->getUploadFileInfo($blogId);
        $relativePath = $this->getCore()->getFullyRelateAttachmentPathByBlogId($blogId, $parsedUrlPath);
        return $sourceUploadInfo['basedir'] . DIRECTORY_SEPARATOR . $relativePath;
    }

    /**
     * Converts wordpress file location to URL (for translation)
     *
     * @param string $file
     *
     * @return string
     */
    private function fileToUrl($file)
    {
        $targetUploadInfo = $this->getCore()->getUploadFileInfo($this->getParams()->getSubmission()->getTargetBlogId());

        return str_replace($targetUploadInfo['basedir'], $targetUploadInfo['baseurl'], $file);
    }

    private function getAttachmentIdByBlogId($url, $blogId)
    {
        $localOriginalFile = $this->urlToFileByBlogId($url, $blogId);
        return $this->getPossibleAttachmentIdByLocalOriginalFile($localOriginalFile, $url);
    }

    private function getPossibleAttachmentIdByLocalOriginalFile($localOriginalFile, $url)
    {
        if (true === FileHelper::testFile($localOriginalFile)) {
            $originalPathinfo = pathinfo($localOriginalFile);
            $possibleId = $this->lookForDirectGuidEntry($url);
            if (false === $possibleId && $this->fileLooksLikeThumbnail($originalPathinfo['filename'])) {
                $originalFilename = preg_replace(
                    StringHelper::buildPattern(static::PATTERN_THUMBNAIL_IDENTITY), '', $originalPathinfo['filename']
                );
                $possibleOriginalUrl = str_replace($originalPathinfo['filename'], $originalFilename, $url);
                $possibleId = $this->lookForDirectGuidEntry($possibleOriginalUrl);
            }
            if (false === $possibleId) {
                $this->getLogger()->error(vsprintf('No \'attachment\' found for url=%s', [$url]));
            }

            return $possibleId;
        }

        return false;
    }

    /**
     * @param string $url
     *
     * @return bool|int
     */
    private function getAttachmentId($url)
    {
        $localOriginalFile = $this->urlToFile($url);
        return $this->getPossibleAttachmentIdByLocalOriginalFile($localOriginalFile, $url);
    }
}
