<?php

namespace Smartling\Helpers;

use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\Helpers\QueryBuilder\Condition\Condition;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBlock;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBuilder;
use Smartling\Helpers\QueryBuilder\QueryBuilder;
use Smartling\Submissions\SubmissionEntity;

class AbsoluteLinkedAttachmentCoreHelper extends RelativeLinkedAttachmentCoreHelper
{
    private const PATTERN_LINK_GENERAL = '<a[^>]+>';

    private function isAbsoluteUrl(string $url): bool
    {
        $parsedUrl = parse_url($url);

        return false !== $parsedUrl
               && array_key_exists('scheme', $parsedUrl)
               && !StringHelper::isNullOrEmpty($parsedUrl['scheme']);
    }

    private function isUrlThumbnail(string $url): bool
    {
        return $this->fileLooksLikeThumbnail(pathinfo($this->urlToFile($url))['filename']);
    }

    /**
     * Searches for $url in `guid` field in `posts` table
     */
    private function lookForDirectGuidEntry(string $url): ?int
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

    private function generateTranslatedUrl(string $originalUrl, SubmissionEntity $submission): string
    {
        $result = $this->core->getAttachmentAbsolutePathBySubmission($submission);

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

    private function processContent(PairReplacerHelper $replacer, string $path): PairReplacerHelper
    {
        $result = $replacer;
        if ($this->isAbsoluteUrl($path)) {
            $attachmentId = $this->getAttachmentId($path);
            if (null !== $attachmentId) {
                $submission = $this->getParams()->getSubmission();
                $sourceBlogId = $submission->getSourceBlogId();
                $targetBlogId = $submission->getTargetBlogId();
                $attachmentSubmission = $this->submissionManager->findOne([
                    SubmissionEntity::FIELD_CONTENT_TYPE => ContentTypeHelper::POST_TYPE_ATTACHMENT,
                    SubmissionEntity::FIELD_SOURCE_BLOG_ID => $sourceBlogId,
                    SubmissionEntity::FIELD_SOURCE_ID => $attachmentId,
                    SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlogId,
                ]);
                if ($attachmentSubmission !== null) {
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

    public function getAttachmentIdByURL(string $url, int $blogId): ?int
    {
        $result = null;
        if ($this->isAbsoluteUrl($url)) {
            $result = $this->getAttachmentIdByBlogId($url, $blogId);
        }
        return $result;
    }

    /**
     * @return int[]
     */
    public function getImagesIdsFromString(string $string, int $blogId): array
    {
        $ids = [];

        $matches = [];
        if (0 < preg_match_all(StringHelper::buildPattern(static::PATTERN_IMAGE_GENERAL), $string, $matches)) {
            foreach ($matches[0] as $match) {
                $ids = $this->addAttachmentId($ids, $match, 'img', 'src', $blogId);
            }
        }
        if (0 < preg_match_all(StringHelper::buildPattern(self::PATTERN_LINK_GENERAL), $string, $matches)) {
            foreach ($matches[0] as $match) {
                $ids = $this->addAttachmentId($ids, $match, 'a', 'href', $blogId);
            }
        }

        return $ids;
    }

    private function addAttachmentId(array $ids, string $tagString, string $tagName, string $attribute, int $blogId): array {
        $path = $this->getAttributeFromTag($tagString, $tagName, $attribute);
        if ($path !== null) {
            $attachmentId = $this->getAttachmentIdByURL($path, $blogId);
            if ($attachmentId !== null) {
                $ids[] = $attachmentId;
            }
        }

        return $ids;
    }

    /**
     * Recursively processes all found strings
     *
     * @param array|string $stringValue
     */
    protected function processString(&$stringValue): void
    {
        $replacer = new PairReplacerHelper();
        if (is_array($stringValue)) {
            foreach ($stringValue as &$value) {
                $this->processString($value);
            }
            unset($value);
        } else {
            $matches = [];
            if (0 < preg_match_all(StringHelper::buildPattern(static::PATTERN_IMAGE_GENERAL), $stringValue, $matches)) {
                foreach ($matches[0] as $match) {
                    $path = $this->getAttributeFromTag($match, 'img', 'src');
                    if ($path !== null) {
                        $replacer = $this->processContent($replacer, $path);
                    }
                }
            }
            if (0 < preg_match_all(StringHelper::buildPattern(self::PATTERN_LINK_GENERAL), $stringValue, $matches)) {
                foreach ($matches[0] as $match) {
                    $path = $this->getAttributeFromTag($match, 'a', 'href');
                    if ($path !== null) {
                        $replacer = $this->processContent($replacer, $path);
                    }
                }
            }
        }
        $stringValue = $replacer->processString($stringValue);
    }

    /**
     * Converts wordpress attachment URL to file location (for source attachment)
     */
    private function urlToFile(string $url): string
    {
        return $this->urlToFileByBlogId($url, $this->getParams()->getSubmission()->getSourceBlogId());
    }

    private function urlToFileByBlogId(string $url, int $blogId): string
    {
        $parsedUrlPath = parse_url($url, PHP_URL_PATH);
        $sourceUploadInfo = $this->core->getUploadFileInfo($blogId);
        $relativePath = $this->core->getFullyRelateAttachmentPathByBlogId($blogId, $parsedUrlPath);
        return $sourceUploadInfo['basedir'] . DIRECTORY_SEPARATOR . $relativePath;
    }

    /**
     * Converts wordpress file location to URL (for translation)
     */
    private function fileToUrl(string $file): string
    {
        $targetUploadInfo = $this->core->getUploadFileInfo($this->getParams()->getSubmission()->getTargetBlogId());

        return str_replace($targetUploadInfo['basedir'], $targetUploadInfo['baseurl'], $file);
    }

    private function getAttachmentIdByBlogId(string $url, int $blogId): ?int
    {
        $localOriginalFile = $this->urlToFileByBlogId($url, $blogId);
        return $this->getPossibleAttachmentIdByLocalOriginalFile($localOriginalFile, $url);
    }

    private function getPossibleAttachmentIdByLocalOriginalFile(string $localOriginalFile, string $url): ?int
    {
        if (true === FileHelper::testFile($localOriginalFile)) {
            $originalPathinfo = pathinfo($localOriginalFile);
            $possibleId = $this->lookForDirectGuidEntry($url);
            if (null === $possibleId && $this->fileLooksLikeThumbnail($originalPathinfo['filename'])) {
                $originalFilename = preg_replace(
                    StringHelper::buildPattern(static::PATTERN_THUMBNAIL_IDENTITY), '', $originalPathinfo['filename']
                );
                $possibleOriginalUrl = str_replace($originalPathinfo['filename'], $originalFilename, $url);
                $possibleId = $this->lookForDirectGuidEntry($possibleOriginalUrl);
            }
            if (null === $possibleId) {
                $this->getLogger()->info(vsprintf('No \'attachment\' found for url=%s', [$url]));
            }

            return $possibleId;
        }

        return null;
    }

    private function getAttachmentId(string $url): ?int
    {
        $localOriginalFile = $this->urlToFile($url);
        return $this->getPossibleAttachmentIdByLocalOriginalFile($localOriginalFile, $url);
    }
}
