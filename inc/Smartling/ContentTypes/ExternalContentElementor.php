<?php

namespace Smartling\ContentTypes;

use Elementor\Core\Documents_Manager;
use ElementorPro\Plugin;
use Smartling\Base\ExportedAPI;
use Smartling\ContentTypes\Elementor\DocumentsManagerWrapper;
use Smartling\ContentTypes\Elementor\ElementFactory;
use Smartling\Extensions\Pluggable;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Helpers\LinkProcessor;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\PluginHelper;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\UserHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Models\ExternalData;
use Smartling\Models\RelatedContentInfo;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class ExternalContentElementor extends ExternalContentAbstract implements ContentTypeModifyingInterface
{
    use LoggerSafeTrait;

    protected const META_FIELD_NAME = '_elementor_data';
    private const META_CONDITIONS_NAME = '_elementor_conditions';

    private array $copyFields = [
        '_elementor_controls_usage',
        '_elementor_css',
        '_elementor_edit_mode',
        '_elementor_page_assets',
        '_elementor_page_settings',
        '_elementor_pro_version',
        '_elementor_template_type',
        '_elementor_version',
    ];

    private array $removeOnUploadFields = [
        'entity' => [
            'post_content',
        ],
        'meta' => [
            self::META_FIELD_NAME,
            '_elementor_element_cache',
        ]
    ];

    public function __construct(
        private ContentTypeHelper $contentTypeHelper,
        private ElementFactory $elementFactory,
        private FieldsFilterHelper $fieldsFilterHelper,
        PluginHelper $pluginHelper,
        private SiteHelper $siteHelper,
        SubmissionManager $submissionManager,
        private UserHelper $userHelper,
        WordpressFunctionProxyHelper $wpProxy,
        private LinkProcessor $linkProcessor,
    )
    {
        $wpProxy->add_action(ExportedAPI::ACTION_AFTER_TARGET_METADATA_WRITTEN, [$this, 'afterMetaWritten']);
        parent::__construct($pluginHelper, $submissionManager, $wpProxy);
    }

    public function afterMetaWritten(SubmissionEntity $submission): void
    {
        if ($submission->getTargetId() === 0) {
            $this->getLogger()->debug('Processing Elementor after meta written hook skipped, targetId=0');
            return;
        }
        $this->siteHelper->withBlog($submission->getTargetBlogId(), function () use ($submission) {
            $supportLevel = $this->getSupportLevel($submission->getContentType(), $submission->getTargetId());
            $documentsManager = $this->getDocumentsManager();
            if ($supportLevel !== Pluggable::NOT_SUPPORTED && $documentsManager !== null) {
                $this->getLogger()->debug(sprintf('Processing Elementor after content written hook, contentType=%s, sourceBlogId=%d, sourceId=%d, submissionId=%d, targetBlogId=%d, targetId=%d, supportLevel=%s', $submission->getContentType(), $submission->getSourceBlogId(), $submission->getSourceId(), $submission->getId(), $submission->getTargetBlogId(), $submission->getTargetId(), $supportLevel));
                $this->userHelper->asAdministratorOrEditor(function () use ($documentsManager, $submission) {
                    try {
                        /** @noinspection PhpParamsInspection */
                        $documentsManager->ajax_save([
                            'editor_post_id' => $submission->getTargetId(),
                            'elements' => json_decode($this->getDataFromPostMeta($submission->getTargetId()),
                                true,
                                512,
                                JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT),
                            'status' => $this->wpProxy->get_post($submission->getTargetId())->post_status,
                        ]);
                    } catch (\Throwable $e) {
                        $this->getLogger()->notice(sprintf("Unable to do Elementor save actions for contentType=%s, submissionId=%d, targetBlogId=%d, targetId=%d: %s (%s)", $submission->getContentType(), $submission->getId(), $submission->getTargetBlogId(), $submission->getTargetId(), $e->getMessage(), $e->getTraceAsString()));
                    }
                });
            }
        });
        $this->getLogger()->info("Done processing Elementor after content written hook");
    }

    public function removeUntranslatableFieldsForUpload(array $source, SubmissionEntity $submission): array
    {
        if ($this->getSupportLevel($submission->getContentType(), $submission->getSourceId()) !== Pluggable::NOT_SUPPORTED) {
            $this->getLogger()->info('Detected elementor data, removing post content and elementor related meta fields');
            foreach (array_merge_recursive(
                ['meta' => array_merge($this->copyFields, [self::META_CONDITIONS_NAME])],
                $this->removeOnUploadFields,
            ) as $key => $value) {
                if (array_key_exists($key, $source)) {
                    foreach ($value as $field) {
                        unset($source[$key][$field]);
                    }
                }
            }
        }

        return $source;
    }

    public function getSupportLevel(string $contentType, ?int $contentId = null): string
    {
        if ($this->contentTypeHelper->isPost($contentType) && $this->getDataFromPostMeta($contentId) !== '') {
            return parent::getSupportLevel($contentType, $contentId);
        }
        return Pluggable::NOT_SUPPORTED;
    }

    private function getData(array $data): ExternalData
    {
        $related = new RelatedContentInfo();
        $strings = [];

        foreach ($data as $array) {
            $element = $this->elementFactory->fromArray($array);
            $related = $related->merge($element->getRelated());
            $strings[] = $element->getTranslatableStrings();
        }

        return new ExternalData(strings: $strings, relatedContentInfo: $related);
    }

    public function getContentFields(SubmissionEntity $submission, bool $raw): array
    {
        return $this->fieldsFilterHelper->flattenArray(
            (new ArrayHelper())->add(
                ...$this->getData($this->readMeta($submission->getSourceId()))->getStrings()
            )
        );
    }

    private function readMeta(int $id): array
    {
        return json_decode($this->getDataFromPostMeta($id), true, 512, JSON_THROW_ON_ERROR);
    }

    public function getMaxVersion(): string
    {
        return '3';
    }

    public function getMinVersion(): string
    {
        return '3';
    }

    public function getPluginId(): string
    {
        return 'elementor';
    }

    public function getPluginPaths(): array
    {
        return ['elementor/elementor.php'];
    }

    public function getRelatedContent(string $contentType, int $contentId): array
    {
        return $this->getData($this->readMeta($contentId))->getRelatedContentInfo()->getRelatedContentList();
    }

    private function mergeElementorData(array $original, array $strings, SubmissionEntity $submission): array
    {
        $result = [];
        foreach ($original as $array) {
            $element = $this->elementFactory->fromArray($array);
            $result[] = $element->setTargetContent(
                $this,
                $this->getData($original)->getRelatedContentInfo(),
                $strings,
                $submission,
            )->toArray();
        }

        return $result;
    }

    public function setContentFields(array $original, array $translation, SubmissionEntity $submission): array
    {
        if (array_key_exists('meta', $original)) {
            foreach ($this->copyFields as $field) {
                if (array_key_exists($field, $original['meta'])) {
                    $value = $original['meta'][$field];
                    $translation['meta'][$field] = is_string($value) ? $this->wpProxy->maybe_unserialize($value) : $value;
                }
            }
            if (array_key_exists(self::META_CONDITIONS_NAME, $original['meta'])) {
                $value = $original['meta'][self::META_CONDITIONS_NAME];
                if (is_string($value)) {
                    $translation['meta'][self::META_CONDITIONS_NAME] = $this->rebuildConditionsField($value, $submission);
                }
            }
        }
        $translation['meta'][self::META_FIELD_NAME] = json_encode($this->mergeElementorData(
            json_decode($original['meta'][self::META_FIELD_NAME] ?? '[]', true, 512, JSON_THROW_ON_ERROR),
            $translation[$this->getPluginId()] ?? [],
            $submission,
        ), JSON_THROW_ON_ERROR);
        unset($translation[$this->getPluginId()]);
        return $translation;
    }

    private function rebuildConditionsField(string $conditions, SubmissionEntity $submission): string
    {
        $value = $this->wpProxy->maybe_unserialize($conditions);
        if (is_array($value)) {
            foreach ($value as $index => $condition) {
                $matches = [];
                preg_match('~(\d+)$~', $condition, $matches);
                $id = $matches[0] ?? null;
                if ($id === null) {
                    continue;
                }
                $related = $this->submissionManager->findOne([
                    SubmissionEntity::FIELD_SOURCE_BLOG_ID => $submission->getSourceBlogId(),
                    SubmissionEntity::FIELD_SOURCE_ID => $id,
                    SubmissionEntity::FIELD_TARGET_BLOG_ID => $submission->getTargetBlogId(),
                ]);
                if ($related !== null) {
                    $value[$index] = str_replace($id, $related->getTargetId(), $condition);
                }
            }
            return serialize($value);
        }
        return $value;
    }

    private function getDocumentsManager(): ?Documents_Manager
    {
        if (class_exists(Plugin::class)) {
            return (new DocumentsManagerWrapper(Plugin::elementor()->documents))->getManagerWithoutDocuments();
        }
        $documentsManagerPath = WP_PLUGIN_DIR . '/elementor/core/documents-manager.php';
        if (file_exists($documentsManagerPath)) {
            try {
                require_once $documentsManagerPath;

                $manager = new Documents_Manager();
                do_action('elementor/documents/register', $manager);
                return $manager;
            } catch (\Throwable) {
                // No documents manager available
            }
        }
        if (class_exists(Documents_Manager::class)) {
            return new Documents_Manager();
        }

        return null;
    }
}
