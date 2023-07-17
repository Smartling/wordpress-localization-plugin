<?php

namespace Smartling\ContentTypes;

use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\PluginHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

abstract class ExternalContentAbstract implements ContentTypePluggableInterface {
    use LoggerSafeTrait;

    protected PluginHelper $pluginHelper;
    protected SubmissionManager $submissionManager;
    protected WordpressFunctionProxyHelper $wpProxy;

    public function __construct(PluginHelper $pluginHelper, SubmissionManager $submissionManager, WordpressFunctionProxyHelper $wpProxy)
    {
        $this->pluginHelper = $pluginHelper;
        $this->submissionManager = $submissionManager;
        $this->wpProxy = $wpProxy;
    }

    public function getSupportLevel(string $contentType, ?int $contentId = null): string
    {
        $result = self::NOT_SUPPORTED;
        $plugins = $this->wpProxy->get_plugins();
        foreach ($this->getPluginPaths() as $path) {
            if (array_key_exists($path, $plugins) && $this->wpProxy->is_plugin_active($path)) {
                if ($this->pluginHelper->versionInRange($plugins[$path]['Version'] ?? 0, $this->getMinVersion(), $this->getMaxVersion())) {
                    return self::SUPPORTED;
                }
                $result = self::VERSION_NOT_SUPPORTED;
            }
        }

        return $result;
    }

    public function getExternalContentTypes(): array
    {
        return [];
    }

    public function getRelatedContent(string $contentType, int $contentId): array
    {
        return [];
    }

    protected function getTargetId(int $sourceBlogId, int $sourceId, int $targetBlogId, string $contentType = ContentTypeHelper::POST_TYPE_ATTACHMENT): ?int
    {
        $this->getLogger()->debug("Searching for target id to replace sourceId=$sourceId");
        $targetSubmission = $this->submissionManager->findOne([
            SubmissionEntity::FIELD_CONTENT_TYPE => $contentType,
            SubmissionEntity::FIELD_SOURCE_BLOG_ID => $sourceBlogId,
            SubmissionEntity::FIELD_SOURCE_ID => $sourceId,
            SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlogId,
        ]);
        if ($targetSubmission === null) {
            return null;
        }
        if ($targetSubmission->getTargetId() === 0) {
            $this->getLogger()->info("Got 0 target attachment id for sourceId=$sourceId");
            return null;
        }

        return $targetSubmission->getTargetId();
    }

    protected function getDataFromPostMeta(int $id)
    {
        return $this->wpProxy->getPostMeta($id, static::META_FIELD_NAME, true);
    }
}
