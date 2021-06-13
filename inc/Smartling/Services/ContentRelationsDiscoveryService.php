<?php

namespace Smartling\Services;

use Exception;
use Psr\Log\LoggerInterface;
use Smartling\ApiWrapperInterface;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Exception\EntityNotFoundException;
use Smartling\Exception\SmartlingDbException;
use Smartling\Exceptions\SmartlingApiException;
use Smartling\Helpers\AbsoluteLinkedAttachmentCoreHelper;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\DateTimeHelper;
use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Helpers\GutenbergBlockHelper;
use Smartling\Helpers\MetaFieldProcessor\DefaultMetaFieldProcessor;
use Smartling\Helpers\MetaFieldProcessor\MetaFieldProcessorAbstract;
use Smartling\Helpers\MetaFieldProcessor\MetaFieldProcessorManager;
use Smartling\Helpers\ShortcodeHelper;
use Smartling\Helpers\StringHelper;
use Smartling\Jobs\JobEntityWithBatchUid;
use Smartling\Models\GutenbergBlock;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Replacers\ContentIdReplacer;
use Smartling\Replacers\ReplacerFactory;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\Tuner\MediaAttachmentRulesManager;

/**
 *
 * ajax service that discovers related items.
 * usage: GET /wp-admin/admin-ajax.php?action=smartling-get-relations&id=48&content-type=post&targetBlogIds=2,3,4,5
 *
 * Response Example:
 *
 * {
 *  "status":"SUCCESS",
 *  "response":{
 *      "data":{
 *          "originalReferences":{
 *              "attachment":[244,232,26,231],
 *              "post":[1],
 *              "page":[2],
 *              "post_tag":[13,14],
 *              "category":[1]
 *          },
 *          "missingTranslatedReferences":{
 *              "2":{"attachment":[244,232,231]},
 *              "3":{"attachment":[244,232,26,231]},
 *              "4":{"attachment":[244,232,26,231],"post":[1],"post_tag":[13,14]},
 *              "5":{"attachment":[244,232,26,231],"post_tag":[13,14]}
 *          }
 *      }
 *  }
 * }
 *
 *
 * blogId is discovered from current active blog via Wordpress Multisite API
 */
class ContentRelationsDiscoveryService extends BaseAjaxServiceAbstract
{
    public const ACTION_NAME = 'smartling-get-relations';

    private const ACTION_NAME_CREATE_SUBMISSIONS = 'smartling-create-submissions';

    private LoggerInterface $logger;
    private ContentHelper $contentHelper;
    private FieldsFilterHelper $fieldFilterHelper;
    private MediaAttachmentRulesManager $mediaAttachmentRulesManager;
    private MetaFieldProcessorManager $metaFieldProcessorManager;
    private ReplacerFactory $replacerFactory;
    private AbsoluteLinkedAttachmentCoreHelper $absoluteLinkedAttachmentCoreHelper;
    private ShortcodeHelper $shortcodeHelper;
    private GutenbergBlockHelper $gutenbergBlockHelper;
    private SubmissionManager $submissionManager;
    private ApiWrapperInterface $apiWrapper;
    private SettingsManager $settingsManager;
    private LocalizationPluginProxyInterface $localizationPluginProxy;

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    public function __construct(
        ContentHelper $contentHelper,
        FieldsFilterHelper $fieldFilterHelper,
        MetaFieldProcessorManager $fieldProcessorManager,
        LocalizationPluginProxyInterface $localizationPluginProxy,
        AbsoluteLinkedAttachmentCoreHelper $absoluteLinkedAttachmentCoreHelper,
        ShortcodeHelper $shortcodeHelper,
        GutenbergBlockHelper $blockHelper,
        SubmissionManager $submissionManager,
        ApiWrapperInterface $apiWrapper,
        MediaAttachmentRulesManager $mediaAttachmentRulesManager,
        ReplacerFactory $replacerFactory,
        SettingsManager $settingsManager
    )
    {
        $this->absoluteLinkedAttachmentCoreHelper = $absoluteLinkedAttachmentCoreHelper;
        $this->apiWrapper = $apiWrapper;
        $this->contentHelper = $contentHelper;
        $this->fieldFilterHelper = $fieldFilterHelper;
        $this->gutenbergBlockHelper = $blockHelper;
        $this->localizationPluginProxy = $localizationPluginProxy;
        $this->logger = MonologWrapper::getLogger(static::class);
        $this->mediaAttachmentRulesManager = $mediaAttachmentRulesManager;
        $this->metaFieldProcessorManager = $fieldProcessorManager;
        $this->replacerFactory = $replacerFactory;
        $this->settingsManager = $settingsManager;
        $this->shortcodeHelper = $shortcodeHelper;
        $this->submissionManager = $submissionManager;
    }

    public function register(): void
    {
        parent::register();
        add_action('wp_ajax_' . static::ACTION_NAME_CREATE_SUBMISSIONS, [$this, 'createSubmissionsHandler']);
    }

    /**
     * @throws SmartlingApiException
     * @throws SmartlingDbException
     */
    protected function getBatchUid(int $sourceBlogId, array $job): string
    {
        return $this
            ->apiWrapper
            ->retrieveBatch(
                $this->settingsManager->getSingleSettingsProfile($sourceBlogId),
                $job['id'],
                'true' === $job['authorize'],
                [
                    'name' => $job['name'],
                    'description' => $job['description'],
                    'dueDate' => [
                        'date' => $job['dueDate'],
                        'timezone' => $job['timeZone'],
                    ],
                ]
            );
    }

    /**
     * This function only returns when testing, WP will stop execution after wp_send_json
     */
    public function bulkUploadHandler(JobEntityWithBatchUid $jobInfo, array $contentIds, string $contentType, int $currentBlogId, array $targetBlogIds): void
    {
        foreach ($targetBlogIds as $targetBlogId) {
            $blogFields = [
                SubmissionEntity::FIELD_SOURCE_BLOG_ID => $currentBlogId,
                SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlogId,
            ];
            foreach ($contentIds as $id) {
                $existing = $this->submissionManager->find(array_merge($blogFields, [
                    SubmissionEntity::FIELD_CONTENT_TYPE => $contentType,
                    SubmissionEntity::FIELD_SOURCE_ID => $id
                ]));

                if (empty($existing)) {
                    $submission = $this->submissionManager->getSubmissionEntity($contentType, $currentBlogId, $id, $targetBlogId, $this->localizationPluginProxy);
                } else {
                    $submission = ArrayHelper::first($existing);
                }
                $submission->setBatchUid($jobInfo->getBatchUid());
                $submission->setJobInfo($jobInfo->getJobInformationEntity());
                $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_NEW);
                $submission->getFileUri();
                $this->submissionManager->storeEntity($submission);
            }
        }
        $this->returnResponse(['status' => 'SUCCESS']);
    }

    /**
     * Handler for POST request that creates submissions for main content and selected relations
     *
     * Request Example:
     *
     *  [
     *      'source'       => ['contentType' => 'post', 'id' => [0 => '48']],
     *      'job'          =>
     *      [
     *          'id'          => 'abcdef123456',
     *          'name'        => '',
     *          'description' => '',
     *          'dueDate'     => '',
     *          'timeZone'    => 'Europe/Kiev',
     *          'authorize'   => 'true',
     *      ],
     *      'targetBlogIds' => '3,2',
     *      'relations'    => {{@see actionHandler }} relations response
     *  ]
     * @var array|string $data
     */
    public function createSubmissionsHandler($data = ''): void
    {
        if (!is_array($data)) {
            $data = $_POST;
        }
        try {
            $contentType = $data['source']['contentType'];
            $curBlogId = $this->contentHelper->getSiteHelper()->getCurrentBlogId();
            $batchUid = $this->getBatchUid($curBlogId, $data['job']);
            $jobInfo = new JobEntityWithBatchUid($batchUid, $data['job']['name'], $data['job']['id'], $this->settingsManager->getSingleSettingsProfile($curBlogId)->getProjectId());
            $targetBlogIds = explode(',', $data['targetBlogIds']);

            if (array_key_exists('ids', $data)) {
                $this->bulkUploadHandler($jobInfo, $data['ids'], $contentType, $curBlogId, $targetBlogIds);
                return;
            }

            foreach ($targetBlogIds as $targetBlogId) {
                $submissionTemplateArray = [
                    SubmissionEntity::FIELD_SOURCE_BLOG_ID => $curBlogId,
                    SubmissionEntity::FIELD_TARGET_BLOG_ID => (int)$targetBlogId,
                ];

                /**
                 * Submission for original content may already exist
                 */
                $searchParams = array_merge($submissionTemplateArray, [
                    SubmissionEntity::FIELD_CONTENT_TYPE => $contentType,
                    SubmissionEntity::FIELD_SOURCE_ID => ArrayHelper::first($data['source']['id']),
                ]);

                $sources = [];

                if (array_key_exists('relations', $data) && is_array($data['relations']) && array_key_exists($targetBlogId, $data['relations'])) {
                    foreach ($data['relations'][$targetBlogId] as $sysType => $ids) {
                        foreach ($ids as $id) {
                            $sources[] = [
                                'type' => $sysType,
                                'id' => $id,
                            ];
                        }
                    }
                }

                $result = $this->submissionManager->find($searchParams, 1);

                if (empty($result)) {
                    $sources[] = [
                        'type' => $contentType,
                        'id' => ArrayHelper::first($data['source']['id']),
                    ];
                } else {
                    $submission = ArrayHelper::first($result);
                    $submission->setBatchUid($jobInfo->getBatchUid());
                    $submission->setJobInfo($jobInfo->getJobInformationEntity());
                    $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_NEW);
                    $this->submissionManager->storeEntity($submission);
                }

                /**
                 * Adding fields to template
                 */
                $submissionTemplateArray[SubmissionEntity::FIELD_BATCH_UID] = $batchUid;
                $submissionTemplateArray[SubmissionEntity::FIELD_STATUS] = SubmissionEntity::SUBMISSION_STATUS_NEW;
                $submissionTemplateArray[SubmissionEntity::FIELD_SUBMISSION_DATE] = DateTimeHelper::nowAsString();

                foreach ($sources as $source) {
                    $submissionArray = array_merge($submissionTemplateArray, [
                        SubmissionEntity::FIELD_CONTENT_TYPE => $source['type'],
                        SubmissionEntity::FIELD_SOURCE_ID => $source['id'],
                    ]);

                    $submission = SubmissionEntity::fromArray($submissionArray, $this->getLogger());

                    // trigger generation of fileUri
                    $submission->getFileUri();
                    $this->submissionManager->storeEntity($submission);
                }
            }

            $this->returnResponse(['status' => 'SUCCESS']);
        } catch (Exception $e) {
            $this->returnError('content.submission.failed', $e->getMessage());
        }
    }

    public function getRequestSource(): array
    {
        return $_GET;
    }

    public function getContentType(): string
    {
        return $this->getRequiredParam('content-type');
    }

    public function getId(): int
    {
        return (int)$this->getRequiredParam('id');
    }

    /**
     * @return int[]
     */
    public function getTargetBlogIds(): array
    {
        $blogs = explode(',', $this->getRequiredParam('targetBlogIds'));

        array_walk($blogs, static function ($el) {
            return (int)$el;
        });

        return array_unique($blogs);
    }

    /**
     * @return \WP_Taxonomy[]
     */
    private function getTaxonomiesForContentType(string $contentType): array
    {
        global $wp_taxonomies;

        $relatedTaxonomies = [];

        foreach ($wp_taxonomies as $taxonomy => $descriptor) {
            /**
             * @var \WP_Taxonomy $descriptor
             */
            if (in_array($contentType, $descriptor->object_type, true)) {
                $relatedTaxonomies[] = $taxonomy;
            }
        }
        return $relatedTaxonomies;
    }

    private function getBackwardRelatedTaxonomies(int $contentId, string $contentType): array
    {
        $detectedReferences = [];
        $relatedTaxonomyTypes = $this->getTaxonomiesForContentType($contentType);

        $terms = wp_get_object_terms($contentId, $relatedTaxonomyTypes);
        foreach ($terms as $term) {
            /**
             * @var \WP_Term $term
             */
            $detectedReferences[$term->taxonomy][] = $term->term_id;
        }

        return $detectedReferences;
    }

    private array $shortcodeFields = [];

    /**
     * @see extractFieldsFromShortcodes();
     * @noinspection PhpUnused
     * @noinspection UnknownInspectionInspection
     */
    public function shortcodeHandler(array $attributes, string $content, string $shortcodeName): void
    {
        foreach ($attributes as $attributeName => $attributeValue) {
            $this->shortcodeFields[$shortcodeName . '/' . $attributeName][] = $attributeValue;
            if (!StringHelper::isNullOrEmpty($content)) {
                $this->shortcodeHelper->renderString($content);
            }
        }
    }

    private function extractFieldsFromShortcodes(string $baseName, string $string): array
    {
        $detectedShortcodes = $this->shortcodeHelper->getRegisteredShortcodes();
        $this->shortcodeHelper->replaceShortcodeHandler($detectedShortcodes, 'shortcodeHandler', $this);
        $this->shortcodeHelper->renderString($string);
        $this->shortcodeHelper->restoreHandlers();
        $fields = [];
        foreach ($this->shortcodeFields as $fName => $fValue) {
            $fields[$baseName . '/' . $fName] = $fValue;
        }

        $this->shortcodeFields = [];
        return $fields;
    }

    private function extractFieldsFromGutenbergBlock(string $basename, string $string): array
    {
        $result = [];
        $blocks = $this->gutenbergBlockHelper->parseBlocks($string);
        foreach ($blocks as $index => $block) {
            $result = $this->addFlattenedBlock($result, $block, $basename, $index);
        }
        return $result;
    }

    private function addFlattenedBlock(array $array, GutenbergBlock $block, string $baseName, int $index): array
    {
        $innerIndex = 0;
        $blockNamePart = "$baseName/{$block->getBlockName()}_$index";
        $attributes = $block->getAttributes();

        foreach ($attributes as $field => $value) {
            $array[$blockNamePart . '/' . $field] = $value;
        }
        foreach ($block->getInnerBlocks() as $innerBlock) {
            $array = $this->addFlattenedBlock($array, $innerBlock, $blockNamePart, $innerIndex++);
        }

        return $array;
    }

    private function getPostContentReferences(string $content): array
    {
        $result = [];
        foreach ($this->gutenbergBlockHelper->parseBlocks($content) as $block) {
            $result = $this->addPostContentReferences($result, $block);
        }

        $result = array_unique($result);

        return ['PostBasedProcessor' => array_combine($result, $result)] ;
    }

    private function addPostContentReferences(array $array, GutenbergBlock $block): array
    {
        foreach ($block->getAttributes() as $attribute => $value) {
            foreach ($this->mediaAttachmentRulesManager->getGutenbergReplacementRules($block->getBlockName(), $attribute) as $rule) {
                try {
                    $replacer = $this->replacerFactory->getReplacer($rule->getReplacerId());
                } catch (EntityNotFoundException $e) {
                    continue;
                }
                if ($replacer instanceof ContentIdReplacer && is_numeric($value)) {
                    $array[] = $value;
                }
            }
        }
        foreach ($block->getInnerBlocks() as $innerBlock) {
            $array = $this->addPostContentReferences($array, $innerBlock);
        }

        return $array;
    }

    public function actionHandler(): void
    {
        $contentType = $this->getContentType();
        $detectedReferences = ['attachment' => []];
        $id = $this->getId();
        $curBlogId = $this->contentHelper->getSiteHelper()->getCurrentBlogId();
        $targetBlogIds = $this->getTargetBlogIds();

        if (!$this->contentHelper->checkEntityExists($curBlogId, $contentType, $id)) {
            $this->returnError('content.not.found', 'Requested content is not found', 404);
        }

        $ioWrapper = $this->contentHelper->getIoFactory()->getMapper($contentType);

        $content = [
            'entity' => $ioWrapper->get($id)->toArray(),
            'meta' => $ioWrapper->get($id)->getMetadata(),
        ];

        $fields = $this->fieldFilterHelper->flattenArray($content);

        /**
         * adding fields from shortcodes
         */
        $extraFields = [];
        foreach ($fields as $fName => $fValue) {
            $extraFields = array_merge($extraFields,
                $this->fieldFilterHelper->flattenArray($this->extractFieldsFromShortcodes($fName, $fValue)));
        }
        $fields = array_merge($fields, $extraFields);

        try {
            /**
             * check if gutenberg exists
             */
            $this->gutenbergBlockHelper->loadExternalDependencies();

            /**
             * adding fields from blocks
             */
            $extraFields = [];
            foreach ($fields as $fName => $fValue) {
                $extraFields = array_merge(
                    $extraFields,
                    $this->fieldFilterHelper->flattenArray($this->extractFieldsFromGutenbergBlock($fName, $fValue))
                );
            }
            $fields = array_merge($fields, $extraFields);
        } catch (Exception $e) {
            $this->getLogger()->info(
                vsprintf(
                    'Gutenberg not detected, skipping search for references in Gutenberg blocks for request %s',
                    [
                        var_export($this->getRequestSource(), true),
                    ]
                )
            );
        }

        foreach ($fields as $fName => $fValue) {
            try {
                $this->getLogger()->debug(vsprintf('Looking for processor for field \'%s\'', [$fName]));
                $processor = $this->metaFieldProcessorManager->getProcessor($fName);
                $this->getLogger()->debug(vsprintf('Detected processor \'%s\' for field \'%s\'',
                    [get_class($processor), $fName]));
                /**
                 * in case that we found simple default processor try to treat as ACF field
                 */
                if ($processor instanceof DefaultMetaFieldProcessor) {
                    $this->getLogger()->debug(vsprintf('Trying to treat \'%s\' field as ACF', [$fName]));
                    $acfTypeDetector = $this->metaFieldProcessorManager->getAcfTypeDetector();
                    $processor = $acfTypeDetector->getProcessorByMetaFields($fName, $content['meta']);
                    if ($processor === false) {
                        $processor = $acfTypeDetector->getProcessorForGutenberg($fName, $fields);
                    }
                }

                /**
                 * If processor is detected
                 */
                if ($processor instanceof MetaFieldProcessorAbstract && 0 !== (int)$fValue) {
                    $shortProcessorName = ArrayHelper::last(explode('\\', get_class($processor)));

                    $detectedReferences[$shortProcessorName][$fValue][] = $fName;
                } else {
                    $detectedReferences['attachment'] = array_merge($detectedReferences['attachment'],
                        $this->absoluteLinkedAttachmentCoreHelper->getImagesIdsFromString($fValue, $curBlogId));
                    $detectedReferences['attachment'] = array_unique($detectedReferences['attachment']);
                }
            } catch (Exception $e) {
                $this
                    ->getLogger()
                    ->warning(
                        vsprintf(
                            'failed searching for processor for field \'%s\'=\'%s\'',
                            [
                                $fName,
                                $fValue,
                            ]
                        )
                    );
            }
        }

        $detectedReferences['taxonomies'] = $this->getBackwardRelatedTaxonomies($id, $contentType);

        if (array_key_exists('post_content', $content['entity'])) {
            $detectedReferences = array_merge($detectedReferences, $this->getPostContentReferences($content['entity']['post_content']));
        }

        $detectedReferences = $this->normalizeReferences($detectedReferences);

        $responseData = [
            'originalReferences' => $detectedReferences,
        ];

        $registeredTypes = get_post_types();
        $taxonomies = $this->contentHelper->getSiteHelper()->getTermTypes();
        foreach ($targetBlogIds as $targetBlogId) {
            foreach ($detectedReferences as $contentType => $ids) {
                if (in_array($contentType, $registeredTypes, true) || in_array($contentType, $taxonomies, true)) {
                    foreach ($ids as $id) {
                        // TODO: find out when a null id gets added to the list, this should not happen
                        if ($id === null) {
                            $this->getLogger()->notice("Null id passed when processing detected references contentType=\"$contentType\"");
                        } elseif (!$this->submissionManager->submissionExists($contentType, $curBlogId, $id, $targetBlogId)) {
                            $responseData['missingTranslatedReferences'][$targetBlogId][$contentType][] = $id;
                        }
                    }
                } else {
                    $this->getLogger()->debug("Excluded $contentType from related submissions");
                }
            }
        }

        $this->returnSuccess(
            [
                'data' => $responseData,
            ]
        );
    }

    protected function normalizeReferences(array $references): array
    {
        $result = [];

        if (isset($references['attachment'])) {
            $result['attachment'] = $references['attachment'];
        }

        if (isset($references['MediaBasedProcessor'])) {
            $result['attachment'] = array_merge(($result['attachment'] ?? []), array_keys($references['MediaBasedProcessor']));
        }

        if (isset($references['PostBasedProcessor'])) {
            $postTypeIds = array_keys($references['PostBasedProcessor']);
            foreach ($postTypeIds as $postTypeId) {
                $post = get_post($postTypeId);
                if ($post instanceof \WP_Post) {
                    $result[$post->post_type][] = $post->ID;
                }
            }
        }

        if (isset($references['TermBasedProcessor'])) {
            $termTypeIds = array_keys($references['TermBasedProcessor']);
            foreach ($termTypeIds as $termTypeId) {
                $term = get_term($termTypeId, '', \ARRAY_A);
                if (is_array($term)) {
                    $result[$term['taxonomy']][] = $termTypeId;
                }
            }
        }

        if (isset($references['taxonomies'])) {
            foreach ($references['taxonomies'] as $taxonomy => $ids) {
                $result[$taxonomy] = $ids;
            }
        }

        foreach ($result as $contentType => $items) {
            $result[$contentType] = array_unique($items);
        }

        return $result;
    }
}
