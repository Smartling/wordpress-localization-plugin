<?php

namespace Smartling\Helpers;

use Smartling\Exception\SmartlingManualRelationsHandlingSubmissionCreationForbiddenException;
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
     * Checks if given URL is absolute
     *
     * @param string $url
     *
     * @return bool
     */
    private function urlIsAbsolute($url)
    {
        $parsedUrl = parse_url($url);

        return false !== $parsedUrl
               && array_key_exists('scheme', $parsedUrl)
               && !StringHelper::isNullOrEmpty($parsedUrl['scheme']);
    }

    /**
     * Checks if given URL is a thumbnail
     *
     * @param $url
     *
     * @return bool
     */
    private function urlIsThumbnail($url)
    {
        $file = $this->urlToFile($url);

        $pathInfo = pathinfo($file);

        return $this->fileLooksLikeThumbnail($pathInfo['filename']);
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

        $data = RawDbQueryHelper::query($query);

        $result = false;

        if (is_array($data) && 1 === count($data)) {
            $resultRow = ArrayHelper::first($data);

            if (is_array($resultRow) && array_key_exists('id', $resultRow)) {
                $result = (int)$resultRow['id'];
            }
        }

        return $result;
    }

    /**
     * @param string           $originalUrl
     * @param SubmissionEntity $submission
     *
     * @return string
     */
    private function generateTranslatedUrl($originalUrl, SubmissionEntity $submission)
    {
        $translatedUrl = $this->getCore()->getAttachmentAbsolutePathBySubmission($submission);

        $result = $translatedUrl;

        if ($this->urlIsThumbnail($originalUrl)) {

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

        if (!$this->urlIsAbsolute($originalUrl)) {
            $result = parse_url($result, PHP_URL_PATH);
        }

        return $result;
    }

    /**
     * @param PairReplacerHelper $replacer
     * @param string             $path
     */
    private function processContent(PairReplacerHelper $replacer, $path)
    {
        if ((false !== $path) && $this->urlIsAbsolute($path)) {
            $attachmentId = $this->getAttachmentId($path);
            if (false !== $attachmentId) {
                try {
                    $attachmentSubmission = $this->getCore()->sendAttachmentForTranslation(
                        $this->getParams()->getSubmission()->getSourceBlogId(),
                        $this->getParams()->getSubmission()->getTargetBlogId(),
                        $attachmentId,
                        $this->getParams()->getSubmission()->getBatchUid(),
                        $this->getParams()->getSubmission()->getIsCloned()
                    );
                } catch (SmartlingManualRelationsHandlingSubmissionCreationForbiddenException $e) {
                    $this->getLogger()->notice(
                        "Skipped sending attachment $attachmentId for translation due to manual relations handling"
                    );
                    return;
                }

                $newPath = $this->generateTranslatedUrl($path, $attachmentSubmission);
                $replacer->addReplacementPair($path, $newPath);
                $this->getLogger()->debug(vsprintf('%s has replaced URL from \'%s\' to \'%s\'', [
                    __CLASS__,
                    $path,
                    $newPath,
                ]));
            } else {
                $this->getLogger()->info(vsprintf('No \'attachment\' found for url=%s', [$path]));
            }
        }
    }

    /**
     * @param string $url
     * @param int    $blogId
     * @return bool|int
     */
    public function getAttachmentIdByURL($url, $blogId)
    {
        $result = false;
        if ((false !== $url) && $this->urlIsAbsolute($url)) {
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
    protected function processString(& $stringValue)
    {
        $replacer = new PairReplacerHelper();
        if (is_array($stringValue)) {
            foreach ($stringValue as $item => & $value) {
                $this->processString($value);
            }
        } else {
            $matches = [];
            if (0 < preg_match_all(StringHelper::buildPattern(static::PATTERN_IMAGE_GENERAL), $stringValue, $matches)) {
                foreach ($matches[0] as $match) {
                    $path = $this->getAttributeFromTag($match, 'img', 'src');
                    $this->processContent($replacer, $path);
                }
            }
            if (0 < preg_match_all(StringHelper::buildPattern(self::PATTERN_LINK_GENERAL), $stringValue, $matches)) {
                foreach ($matches[0] as $match) {
                    $path = $this->getAttributeFromTag($match, 'a', 'href');
                    $this->processContent($replacer, $path);
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
        $localOriginalPath = $sourceUploadInfo['basedir'] . DIRECTORY_SEPARATOR . $relativePath;
        return $localOriginalPath;
    }

    /**
     * Converts wordpress file location to URL (for translation)
     *
     * @param $file
     *
     * @return mixed
     */
    private function fileToUrl($file)
    {

        $targetUploadInfo = $this->getCore()->getUploadFileInfo($this->getParams()->getSubmission()->getTargetBlogId());

        $url = str_replace($targetUploadInfo['basedir'], $targetUploadInfo['baseurl'], $file);

        return $url;
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
