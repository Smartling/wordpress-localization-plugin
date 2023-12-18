<?php

namespace Smartling\ContentTypes;

use Smartling\ContentTypes\Elementor\ElementFactory;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\PluginHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Helpers\WordpressLinkHelper;
use Smartling\Models\ExternalData;
use Smartling\Models\RelatedContentInfo;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class ExternalContentElementor extends ExternalContentAbstract implements ContentTypeModifyingInterface
{
    use LoggerSafeTrait;

    public const CONTENT_TYPE_ELEMENTOR_LIBRARY = 'elementor_library';
    protected const META_FIELD_NAME = '_elementor_data';
    private const PROPERTY_TEMPLATE_ID = 'templateID';

    private array $copyFields = [
        '_elementor_controls_usage',
        '_elementor_css',
        '_elementor_edit_mode',
        '_elementor_page_assets',
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
        ]
    ];

    public function __construct(
        private ContentTypeHelper $contentTypeHelper,
        private ElementFactory $elementFactory,
        private FieldsFilterHelper $fieldsFilterHelper,
        PluginHelper $pluginHelper,
        SubmissionManager $submissionManager,
        WordpressFunctionProxyHelper $wpProxy,
        private WordpressLinkHelper $wpLinkHelper,
    )
    {
        parent::__construct($pluginHelper, $submissionManager, $wpProxy);
    }

    public function alterContentFieldsForUpload(array $source): array
    {
        foreach (array_merge_recursive(['meta' => $this->copyFields], $this->removeOnUploadFields) as $key => $value) {
            if (array_key_exists($key, $source)) {
                foreach ($value as $field) {
                    unset($source[$key][$field]);
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
        return self::NOT_SUPPORTED;
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
                $this->getData($original)->getRelatedContentInfo(),
                $strings,
                $submission,
                $this->submissionManager,
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
        }
        $translation['meta'][self::META_FIELD_NAME] = json_encode($this->mergeElementorData(
            json_decode($original['meta'][self::META_FIELD_NAME] ?? '[]', true, 512, JSON_THROW_ON_ERROR),
            $translation[$this->getPluginId()] ?? [],
            $submission,
        ), JSON_THROW_ON_ERROR);
        unset($translation[$this->getPluginId()]);
        return $translation;
    }
}
