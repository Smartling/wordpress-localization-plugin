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

    public function canHandle(string $contentType, int $contentId): bool
    {
        $plugins = $this->wpProxy->get_plugins();
        if (array_key_exists($this->getPluginPath(), $plugins)) {
            return $this->wpProxy->is_plugin_active($this->getPluginPath()) &&
                $this->pluginHelper->versionInRange($plugins[$this->getPluginPath()]['Version'] ?? '0', $this->getMinVersion(), $this->getMaxVersion());
        }

        return false;
    }

    public function getRelatedContent(string $contentType, int $contentId): array
    {
        return [];
    }

    protected function getTargetAttachmentId(int $sourceBlogId, int $sourceId, int $targetBlogId): ?int
    {
        $targetSubmissions = $this->submissionManager->find([
            SubmissionEntity::FIELD_CONTENT_TYPE => ContentTypeHelper::POST_TYPE_ATTACHMENT,
            SubmissionEntity::FIELD_SOURCE_BLOG_ID => $sourceBlogId,
            SubmissionEntity::FIELD_SOURCE_ID => $sourceId,
            SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlogId,
        ]);
        switch (count($targetSubmissions)) {
            case 0:
                $this->getLogger()->debug('No submissions found while getting target attachmentId for sourceId=' . $sourceId);
                break;
            case 1:
                $targetId = $targetSubmissions[0]->getTargetId();
                if ($targetId !== 0) {
                    return $targetId;
                }
                $this->getLogger()->info('Got 0 target attachment id for sourceId=' . $sourceId);
                break;
            default:
                $this->getLogger()->notice('Found more than one submissions while getting target attachmentId for sourceId=' . $sourceId);
        }

        return null;
    }

    protected function getDataFromPostMeta(int $id)
    {
        return $this->wpProxy->getPostMeta($id, static::META_FIELD_NAME, true);
    }
}
