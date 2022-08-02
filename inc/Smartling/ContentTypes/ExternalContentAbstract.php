<?php

namespace Smartling\ContentTypes;

use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\PluginHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

abstract class ExternalContentAbstract implements ContentTypePluggableInterface {
    use LoggerSafeTrait;

    public function canHandle(ContentTypeHelper $contentTypeHelper, PluginHelper $pluginHelper, WordpressFunctionProxyHelper $wpProxy, int $contentId, string $contentType): bool
    {
        $activePlugins = $wpProxy->wp_get_active_network_plugins();
        $plugins = $wpProxy->get_plugins();
        foreach ($activePlugins as $plugin) {
            $parts = array_reverse(explode('/', $plugin));
            if (count($parts) < 2) {
                continue;
            }
            $path = implode('/', [$parts[1], $parts[0]]);
            if ($path === $this->getPluginPath()) {
                if (!array_key_exists($path, $plugins)) {
                    return false;
                }

                return $pluginHelper->versionInRange($plugins[$path]['Version'] ?? '0', $this->getMinVersion(), $this->getMaxVersion());
            }
        }

        return false;
    }

    public function getRelatedContent(string $contentType, int $id): array
    {
        return [];
    }

    protected function getTargetAttachmentId(SubmissionManager $submissionManager, SubmissionEntity $submission, int $attachmentId): ?int
    {
        $targetSubmissions = $submissionManager->find([
            SubmissionEntity::FIELD_SOURCE_BLOG_ID => $submission->getSourceBlogId(),
            SubmissionEntity::FIELD_SOURCE_ID => $attachmentId,
            SubmissionEntity::FIELD_TARGET_BLOG_ID => $submission->getTargetBlogId(),
        ]);
        switch (count($targetSubmissions)) {
            case 0:
                $this->getLogger()->debug('No submissions found while getting target attachmentId for sourceId=' . $attachmentId);
                break;
            case 1:
                $targetId = $targetSubmissions[0]->getTargetId();
                if ($targetId !== 0) {
                    return $targetId;
                }
                $this->getLogger()->info('Got 0 target attachment id for sourceId=' . $attachmentId);
                break;
            default:
                $this->getLogger()->notice('Found more than one submissions while getting target attachmentId for sourceId=' . $attachmentId);
        }

        return null;
    }
}
