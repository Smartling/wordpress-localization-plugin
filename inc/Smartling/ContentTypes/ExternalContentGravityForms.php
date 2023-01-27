<?php

namespace Smartling\ContentTypes;

use Smartling\DbAl\WordpressContentEntities\GravityFormsFormHandler;
use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Helpers\GutenbergBlockHelper;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\PluginHelper;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Processors\ContentEntitiesIOFactory;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class ExternalContentGravityForms extends ExternalContentAbstract implements ContentTypeModifyingInterface {
    use LoggerSafeTrait;

    public const CONTENT_TYPE = 'gravityforms-form';
    private ContentTypeHelper $contentTypeHelper;
    private GravityFormsFormHandler $handler;
    private GutenbergBlockHelper $gutenbergBlockHelper;
    private SiteHelper $siteHelper;
    private FieldsFilterHelper $fieldsFilterHelper;

    public function __construct(
        ContentEntitiesIOFactory $contentEntitiesIOFactory,
        ContentTypeManager $contentTypeManager,
        ContentTypeHelper $contentTypeHelper,
        FieldsFilterHelper $fieldsFilterHelper,
        GravityFormsForm $contentType,
        GravityFormsFormHandler $handler,
        GutenbergBlockHelper $gutenbergBlockHelper,
        PluginHelper $pluginHelper,
        SiteHelper $siteHelper,
        SubmissionManager $submissionManager,
        WordpressFunctionProxyHelper $wpProxy,
    ) {
        parent::__construct($pluginHelper, $submissionManager, $wpProxy);
        $this->contentTypeHelper = $contentTypeHelper;
        $this->fieldsFilterHelper = $fieldsFilterHelper;
        $this->gutenbergBlockHelper = $gutenbergBlockHelper;
        $this->handler = $handler;
        $this->siteHelper = $siteHelper;
        if (parent::canHandle(self::CONTENT_TYPE)) {
            $contentEntitiesIOFactory->registerHandler(self::CONTENT_TYPE, $handler);
            $contentTypeManager->addDescriptor($contentType);
        }
    }

    public function alterContentFieldsForUpload(array $source): array
    {
        unset($source['display_meta']);
        return $source;
    }

    public function canHandle(string $contentType, ?int $contentId = null): bool
    {
        return parent::canHandle($contentType, $contentId) &&
            ($contentType === self::CONTENT_TYPE || $this->contentTypeHelper->isPost($contentType));
    }

    public function getContentFields(SubmissionEntity $submission, bool $raw): array
    {
        /*$fields = $this->siteHelper->withBlog($submission->getSourceBlogId(), function () use ($submission) {
            return [
                'entity' => $this->handler->getFormData($submission->getSourceId()),
                'meta' => $this->handler->getMeta($submission->getSourceId()),
            ];
        });*/
        $form = $this->handler->getFormData($submission->getSourceId());
        $meta = $this->handler->getMeta($submission->getSourceId());
        $displayMeta = json_decode($meta['display_meta'], true, 512, JSON_THROW_ON_ERROR);

        $confirmations = [];
        foreach ($displayMeta['confirmations'] as $key => $confirmation) {
            $confirmations[$key] = [
                'name' => $confirmation['name'],
                'message' => $confirmation['message'],
            ];
        }

        return [
            'title' => $form['title'],
            'displayMeta' => [
                'title' => $displayMeta['title'],
                'description' => $displayMeta['description'],
                'button' => [
                    'text' => $displayMeta['button']['text'],
                ],
                'fields' => array_map(static function ($field) {
                    return [
                        'label' => $field['label'],
                        'adminLabel' => $field['adminLabel'],
                        'errorMessage' => $field['errorMessage'],
                        'description' => $field['description'],
                        'placeholder' => $field['placeholder'],
                        'defaultValue' => $field['defaultValue'],
                        'checkboxLabel' => $field['checkboxLabel'],
                    ];
                }, $displayMeta['fields'] ?? []),
                'confirmations' => $confirmations,
                'customRequiredIndicator' => $displayMeta['customRequiredIndicator'],
                'buttonText' => $displayMeta['buttonText'],
                'saveButtonText' => $displayMeta['saveButtonText'],
                'limitEntriesMessage' => $displayMeta['limitEntriesMessage'],
                'schedulePendingMessage' => $displayMeta['schedulePendingMessage'],
                'scheduleMessage' => $displayMeta['scheduleMessage'],
                'requireLoginMessage' => $displayMeta['requireLoginMessage'],
                'save' => [
                    'button' => [
                        'text' => $displayMeta['save']['button']['text'],
                    ],
                ],
            ],
        ];
    }

    public function getExternalContentTypes(): array
    {
        return [self::CONTENT_TYPE];
    }

    public function getMaxVersion(): string
    {
        return '2.5.9.3';
    }

    public function getMinVersion(): string
    {
        return '2.5.9.3';
    }

    public function getPluginId(): string
    {
        return 'gravity_forms';
    }

    public function getPluginPath(): string
    {
        return 'gravityforms/gravityforms.php';
    }

    public function getRelatedContent(string $contentType, int $contentId): array
    {
        $result = [];
        foreach ($this->gutenbergBlockHelper->parseBlocks($this->wpProxy->get_post($contentId, ARRAY_A)['post_content'] ?? '') as $block) {
            if ($block->getBlockName() === 'gravityforms/form') {
                foreach ($block->getAttributes() as $attribute => $value) {
                    if ($attribute === 'formId') {
                        $result[self::CONTENT_TYPE][] = (int)$value;
                    }
                }
            }
        }

        return $result;
    }

    public function setContentFields(array $original, array $translation, SubmissionEntity $submission): ?array
    {
        $result = $original;
        $result['entity']['title'] = $translation[$this->getPluginId()]['title'];
        $displayMeta = json_decode($result['entity']['displayMeta'], true, 512, JSON_THROW_ON_ERROR);
        $result['entity']['displayMeta'] = json_encode($this->fieldsFilterHelper->structurizeArray(array_merge(
            $this->fieldsFilterHelper->flattenArray($displayMeta),
            $this->fieldsFilterHelper->flattenArray($translation[$this->getPluginId()]['displayMeta']),
        )), JSON_THROW_ON_ERROR);
        $translation['entity'] = $result['entity'];
        unset($translation[$this->getPluginId()]);
        return $translation;
    }
}
