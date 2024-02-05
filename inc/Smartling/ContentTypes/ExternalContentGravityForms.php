<?php

namespace Smartling\ContentTypes;

use Smartling\DbAl\WordpressContentEntities\GravityFormFormData;
use Smartling\DbAl\WordpressContentEntities\GravityFormsFormHandler;
use Smartling\Extensions\Pluggable;
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
        if (parent::getSupportLevel(self::CONTENT_TYPE) === Pluggable::SUPPORTED) {
            $contentEntitiesIOFactory->registerHandler(self::CONTENT_TYPE, $handler);
            $contentTypeManager->addDescriptor($contentType);
        }
    }

    public function removeUntranslatableFieldsForUpload(array $source): array
    {
        unset($source['entity']['displayMeta']);
        return $source;
    }

    public function getSupportLevel(string $contentType, ?int $contentId = null): string
    {
        if ($contentType === self::CONTENT_TYPE || $this->contentTypeHelper->isPost($contentType)) {
            return parent::getSupportLevel($contentType, $contentId);
        }
        return Pluggable::NOT_SUPPORTED;
    }

    public function getContentFields(SubmissionEntity $submission, bool $raw): array
    {
        $formData = $this->siteHelper->withBlog($submission->getSourceBlogId(), function () use ($submission) {
            return $this->handler->getFormData($submission->getSourceId());
        });
        if (!$formData instanceof GravityFormFormData) {
            throw new \RuntimeException(GravityFormFormData::class . ' expected');
        }
        $displayMeta = json_decode($formData->getDisplayMeta(), true, 512, JSON_THROW_ON_ERROR);

        $confirmations = [];
        foreach ($displayMeta['confirmations'] ?? [] as $key => $confirmation) {
            $confirmations[$key] = [
                'name' => $confirmation['name'],
                'message' => $confirmation['message'],
            ];
        }

        return [
            'title' => $formData->getTitle(),
            'displayMeta' => [
                'title' => $displayMeta['title'],
                'button' => [
                    'text' => $displayMeta['button']['text'],
                ],
                'buttonText' => $displayMeta['buttonText'],
                'confirmations' => $confirmations,
                'customRequiredIndicator' => $displayMeta['customRequiredIndicator'],
                'description' => $displayMeta['description'],
                'fields' => array_map(static function ($field) {
                    $choices = $field['choices'] ?? [];
                    if (is_array($field['choices'])) {
                        $choices = array_map(static function ($choice) {
                            return [
                                'text' => $choice['text'],
                                'value' => $choice['value'],
                            ];
                        }, $field['choices']);
                    }

                    $inputs = $field['inputs'] ?? [];
                    if (is_array($field['inputs'])) {
                        $inputs = array_map(static function ($input) {
                            return [
                                'label' => $input['label'],
                            ];
                        }, $field['inputs']);
                    }

                    return [
                        'adminLabel' => $field['adminLabel'],
                        'checkboxLabel' => $field['checkboxLabel'] ?? '',
                        'choices' => $choices,
                        'content' => $field['content'] ?? '',
                        'defaultValue' => $field['defaultValue'],
                        'description' => $field['description'],
                        'errorMessage' => $field['errorMessage'],
                        'inputs' => $inputs,
                        'label' => $field['label'],
                        'placeholder' => $field['placeholder'],
                    ];
                }, $displayMeta['fields'] ?? []),
                'limitEntriesMessage' => $displayMeta['limitEntriesMessage'],
                'requireLoginMessage' => $displayMeta['requireLoginMessage'],
                'save' => [
                    'button' => [
                        'text' => $displayMeta['save']['button']['text'],
                    ],
                ],
                'saveButtonText' => $displayMeta['saveButtonText'],
                'schedulePendingMessage' => $displayMeta['schedulePendingMessage'],
                'scheduleMessage' => $displayMeta['scheduleMessage'],
            ],
        ];
    }

    public function getExternalContentTypes(): array
    {
        return [self::CONTENT_TYPE];
    }

    public function getMaxVersion(): string
    {
        return '2.7';
    }

    public function getMinVersion(): string
    {
        return '2.5';
    }

    public function getPluginId(): string
    {
        return 'gravity_forms';
    }

    public function getPluginPaths(): array
    {
        return ['gravityforms/gravityforms.php'];
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
        if (!array_key_exists($this->getPluginId(), $translation)) {
            return null;
        }
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
