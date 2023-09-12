<?php

namespace Smartling\WP\Table;

use Smartling\DbAl\WordpressContentEntities\EntityHandler;
use Smartling\DbAl\WordpressContentEntities\PostEntityStd;
use Smartling\DbAl\WordpressContentEntities\TaxonomyEntityStd;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\SiteHelper;
use Smartling\Models\DuplicateSubmissionDetails;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\WP\Controller\DuplicateSubmissionsCleaner;

class DuplicateSubmissions extends \WP_List_Table {
    private array $settings = [
        'singular' => 'duplicate submission',
        'plural' => 'duplicate submissions',
        'ajax' => false,
    ];
    /**
     * @var EntityHandler[] $wrappers
     */
    private array $handlers = [];

    public function __construct(
        private ContentHelper $contentHelper,
        private SiteHelper $siteHelper,
        private SubmissionManager $submissionManager,
        private DuplicateSubmissionDetails $duplicateSubmissionDetails,
        private string $nonce,
    ) {
        parent::__construct($this->settings);
    }

    public function getDuplicateSubmissionDetails(): DuplicateSubmissionDetails
    {
        return $this->duplicateSubmissionDetails;
    }

    /**
     * @param array $item
     * @param string $column_name
     * @return mixed
     */
    public function column_default($item, $column_name)
    {
        return $item[$column_name];
    }

    public function get_columns(): array
    {
        return [
            'targetId' => 'Target content ID',
            'targetEdit' => 'Target edit link',
            'action' => 'Delete',
        ];

    }

    public function prepare_items(): void
    {
        $data = [];
        $this->_column_headers = [$this->get_columns(), [], []];
        $contentType = $this->duplicateSubmissionDetails->getContentType();
        $sourceId = $this->duplicateSubmissionDetails->getSourceId();

        $data[] = $this->siteHelper->withBlog(
            $this->duplicateSubmissionDetails->getTargetBlogId(),
            function () use ($contentType, $sourceId) {
                $data = [];
                foreach (
                    $this->submissionManager->find([
                        SubmissionEntity::FIELD_CONTENT_TYPE => $contentType,
                        SubmissionEntity::FIELD_SOURCE_BLOG_ID => $this->duplicateSubmissionDetails->getSourceBlogId(),
                        SubmissionEntity::FIELD_SOURCE_ID => $sourceId,
                        SubmissionEntity::FIELD_TARGET_BLOG_ID => $this->duplicateSubmissionDetails->getTargetBlogId(),
                    ]) as $target
                ) {
                    $editLink = $this->getEditLink($contentType, $target->getTargetId());
                    $data[] = [
                        'targetId' => $target->getTargetId(),
                        'targetEdit' => $editLink === null ? '' : "<a href='$editLink'>Edit</a>",
                        'action' => '<a href="' . get_admin_url(
                                $this->duplicateSubmissionDetails->getSourceBlogId(),
                                'admin.php?page=' . DuplicateSubmissionsCleaner::SLUG .
                                "&action=delete&id={$target->getId()}&_wpnonce={$this->nonce}" . '">Delete</a>'
                            ),
                    ];
                }

                return $data;
            });

        $this->items = array_merge(...$data);
    }

    public function getEditLink(string $contentType, int $id): ?string
    {
        if ($id === 0) {
            return null;
        }
        if (!array_key_exists($contentType, $this->handlers)) {
            $this->handlers[$contentType] = $this->contentHelper->getWrapper($contentType);
        }
        $handler = $this->handlers[$contentType];
        if ($contentType === 'attachment') {
            $source = get_admin_url(path: '/upload.php?item=' . $id);
        } elseif ($handler instanceof PostEntityStd) {
            $source = get_edit_post_link($id, 'href');
        } elseif ($handler instanceof TaxonomyEntityStd) {
            $source = get_edit_term_link($id, $contentType);
        } else {
            $source = null;
        }

        return $source;
    }
}
