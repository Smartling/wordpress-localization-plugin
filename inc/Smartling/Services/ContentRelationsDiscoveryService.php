<?php

namespace Smartling\Services;

use Exception;
use Smartling\ApiWrapperInterface;
use Smartling\ContentTypes\ContentTypeManager;
use Smartling\ContentTypes\ContentTypeNavigationMenu;
use Smartling\ContentTypes\ContentTypeNavigationMenuItem;
use Smartling\ContentTypes\ExternalContentManager;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\DbAl\WordpressContentEntities\EntityWithMetadata;
use Smartling\Exception\EntityNotFoundException;
use Smartling\Exception\SmartlingGutenbergParserNotFoundException;
use Smartling\Exception\SmartlingHumanReadableException;
use Smartling\Exception\SmartlingInvalidFactoryArgumentException;
use Smartling\Extensions\Acf\AcfDynamicSupport;
use Smartling\Helpers\AbsoluteLinkedAttachmentCoreHelper;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\CustomMenuContentTypeHelper;
use Smartling\Helpers\DateTimeHelper;
use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Helpers\GutenbergBlockHelper;
use Smartling\Helpers\MetaFieldProcessor\DefaultMetaFieldProcessor;
use Smartling\Helpers\MetaFieldProcessor\MetaFieldProcessorAbstract;
use Smartling\Helpers\MetaFieldProcessor\MetaFieldProcessorManager;
use Smartling\Helpers\ShortcodeHelper;
use Smartling\Helpers\StringHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Jobs\JobEntityWithBatchUid;
use Smartling\Models\UserCloneRequest;
use Smartling\Models\DetectedRelations;
use Smartling\Models\GutenbergBlock;
use Smartling\Models\JobInformation;
use Smartling\Models\UserTranslationRequest;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Replacers\ContentIdReplacer;
use Smartling\Replacers\ReplacerFactory;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionFactory;
use Smartling\Submissions\SubmissionManager;
use Smartling\Tuner\MediaAttachmentRulesManager;
use Smartling\Vendor\Psr\Log\LoggerInterface;
use Smartling\Vendor\Smartling\AuditLog\Params\CreateRecordParameters;
use Smartling\Vendor\Smartling\Exceptions\SmartlingApiException;

class ContentRelationsDiscoveryService
{
    public const POST_BASED_PROCESSOR = 'PostBasedProcessor';
    private AcfDynamicSupport $acfDynamicSupport;
    private ContentTypeManager $contentTypeManager;
    private ExternalContentManager $externalContentManager;
    private LoggerInterface $logger;
    private ContentHelper $contentHelper;
    private FieldsFilterHelper $fieldFilterHelper;
    private MediaAttachmentRulesManager $mediaAttachmentRulesManager;
    private MetaFieldProcessorManager $metaFieldProcessorManager;
    private ReplacerFactory $replacerFactory;
    private AbsoluteLinkedAttachmentCoreHelper $absoluteLinkedAttachmentCoreHelper;
    private ShortcodeHelper $shortcodeHelper;
    private GutenbergBlockHelper $gutenbergBlockHelper;
    private SubmissionFactory $submissionFactory;
    private SubmissionManager $submissionManager;
    private ApiWrapperInterface $apiWrapper;
    private SettingsManager $settingsManager;
    private LocalizationPluginProxyInterface $localizationPluginProxy;
    private CustomMenuContentTypeHelper $menuHelper;
    private WordpressFunctionProxyHelper $wordpressProxy;

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    public function __construct(
        AcfDynamicSupport $acfDynamicSupport,
        ContentHelper $contentHelper,
        ContentTypeManager $contentTypeManager,
        FieldsFilterHelper $fieldFilterHelper,
        MetaFieldProcessorManager $fieldProcessorManager,
        LocalizationPluginProxyInterface $localizationPluginProxy,
        AbsoluteLinkedAttachmentCoreHelper $absoluteLinkedAttachmentCoreHelper,
        ShortcodeHelper $shortcodeHelper,
        GutenbergBlockHelper $blockHelper,
        SubmissionFactory $submissionFactory,
        SubmissionManager $submissionManager,
        ApiWrapperInterface $apiWrapper,
        MediaAttachmentRulesManager $mediaAttachmentRulesManager,
        ReplacerFactory $replacerFactory,
        SettingsManager $settingsManager,
        CustomMenuContentTypeHelper $menuHelper,
        ExternalContentManager $externalContentManager,
        WordpressFunctionProxyHelper $wordpressProxy
    )
    {
        $this->absoluteLinkedAttachmentCoreHelper = $absoluteLinkedAttachmentCoreHelper;
        $this->acfDynamicSupport = $acfDynamicSupport;
        $this->apiWrapper = $apiWrapper;
        $this->contentHelper = $contentHelper;
        $this->contentTypeManager = $contentTypeManager;
        $this->externalContentManager = $externalContentManager;
        $this->fieldFilterHelper = $fieldFilterHelper;
        $this->gutenbergBlockHelper = $blockHelper;
        $this->localizationPluginProxy = $localizationPluginProxy;
        $this->logger = MonologWrapper::getLogger(static::class);
        $this->mediaAttachmentRulesManager = $mediaAttachmentRulesManager;
        $this->metaFieldProcessorManager = $fieldProcessorManager;
        $this->replacerFactory = $replacerFactory;
        $this->settingsManager = $settingsManager;
        $this->shortcodeHelper = $shortcodeHelper;
        $this->submissionFactory = $submissionFactory;
        $this->submissionManager = $submissionManager;
        $this->menuHelper = $menuHelper;
        $this->wordpressProxy = $wordpressProxy;
    }

    /**
     * @throws SmartlingApiException
     */
    protected function getBatchUid(ConfigurationProfileEntity $profile, JobInformation $job): string
    {
        return $this
            ->apiWrapper
            ->retrieveBatch(
                $profile,
                $job->getId(),
                $job->isAuthorize(),
                [
                    'name' => $job->getName(),
                    'description' => $job->getDescription(),
                    'dueDate' => [
                        'date' => $job->getDueDate(),
                        'timezone' => $job->getTimeZone(),
                    ],
                ]
            );
    }

    public function bulkUpload(JobEntityWithBatchUid $jobInfo, array $contentIds, string $contentType, int $currentBlogId, array $targetBlogIds): void
    {
        $this->getLogger()->debug("Bulk upload request, contentIds=" . json_encode($contentIds));
        foreach ($targetBlogIds as $targetBlogId) {
            $blogFields = [
                SubmissionEntity::FIELD_SOURCE_BLOG_ID => $currentBlogId,
                SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlogId,
            ];
            foreach ($contentIds as $id) {
                $existing = $this->submissionManager->find(array_merge($blogFields, [
                    SubmissionEntity::FIELD_CONTENT_TYPE => $contentType,
                    SubmissionEntity::FIELD_SOURCE_ID => $id,
                ]));

                if (empty($existing)) {
                    $submission = $this->submissionManager->getSubmissionEntity($contentType, $currentBlogId, $id, $targetBlogId, $this->localizationPluginProxy);
                } else {
                    $submission = ArrayHelper::first($existing);
                }
                $submission->setBatchUid($jobInfo->getBatchUid());
                $submission->setJobInfo($jobInfo->getJobInformationEntity());
                $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_NEW);
                $submission->generateFileUri();
                $title = $this->getTitle($submission);
                if ($title !== '') {
                    $submission->setSourceTitle($title);
                }
                $submission = $this->submissionManager->storeEntity($submission);
                $this->logSubmissionCreated($submission, 'Bulk upload request');
                if ($submission->getContentType() === ContentTypeNavigationMenu::WP_CONTENT_TYPE) {
                    $menuItemIds = array_reduce($this->menuHelper->getMenuItems($submission->getSourceId(), $submission->getSourceBlogId()), static function ($carry, $item) {
                        $carry[] = $item->ID;
                        return $carry;
                    }, []);
                    $this->bulkUpload($jobInfo, $menuItemIds, ContentTypeNavigationMenuItem::WP_CONTENT_TYPE, $currentBlogId, [$targetBlogId]);
                }
            }
        }
    }

    public function clone(UserCloneRequest $request): void
    {
        $sourceBlogId = $this->contentHelper->getSiteHelper()->getCurrentBlogId();
        $submissionArray = [
            SubmissionEntity::FIELD_SOURCE_BLOG_ID => $sourceBlogId,
        ];
        $submissions = [];

        foreach ($request->getTargetBlogIds() as $targetBlogId) {
            $submissionArray[SubmissionEntity::FIELD_TARGET_BLOG_ID] = $targetBlogId;
            $sources = $this->getSources($request, $targetBlogId);

            $sources[] = [
                'id' => $request->getContentId(),
                'type' => $request->getContentType(),
            ];

            foreach ($sources as $source) {
                $submissionArray[SubmissionEntity::FIELD_CONTENT_TYPE] = $source['type'];
                $submissionArray[SubmissionEntity::FIELD_SOURCE_ID] = (int)$source['id'];
                $existing = $this->submissionManager->findTargetBlogSubmission(
                    $submissionArray[SubmissionEntity::FIELD_CONTENT_TYPE],
                    $submissionArray[SubmissionEntity::FIELD_SOURCE_BLOG_ID],
                    $submissionArray[SubmissionEntity::FIELD_SOURCE_ID],
                    $submissionArray[SubmissionEntity::FIELD_TARGET_BLOG_ID],
                );
                if ($existing instanceof SubmissionEntity) {
                    $submission = $existing;
                    if ($submission->isLocked()) {
                        $this->getLogger()->debug('Skipping cloning for submissionId=' . $submission->getId() . ', because it is locked');
                        continue;
                    }
                    $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_NEW);
                } else {
                    $submissionArray[SubmissionEntity::FIELD_STATUS] = SubmissionEntity::SUBMISSION_STATUS_NEW;
                    $submissionArray[SubmissionEntity::FIELD_SUBMISSION_DATE] = DateTimeHelper::nowAsString();
                    $submission = $this->submissionFactory->fromArray($submissionArray);
                    $title = $this->getTitle($submission);
                    if ($title !== '') {
                        $submission->setSourceTitle($title);
                    }
                    $submission->generateFileUri();
                }
                $submission->setIsCloned(1);
                $submissions[] = $submission;
            }
        }
        $this->submissionManager->storeSubmissions($submissions);
    }

    public function createSubmissions(UserTranslationRequest $request): void
    {
        $curBlogId = $this->contentHelper->getSiteHelper()->getCurrentBlogId();
        $profile = $this->settingsManager->getSingleSettingsProfile($curBlogId);
        $job = $request->getJobInformation();
        $batchUid = $this->getBatchUid($profile, $job);
        $jobInfo = new JobEntityWithBatchUid($batchUid, $job->getName(), $job->getId(), $profile->getProjectId());

        if ($request->isBulk()) {
            $this->bulkUpload($jobInfo, $request->getIds(), $request->getContentType(), $curBlogId, $request->getTargetBlogIds());
            return;
        }

        $relatedIds = [];
        try {
            foreach ($request->getRelationsOrdered() as $relations) {
                foreach ($relations as $content) {
                    foreach ($content as $contentType => $contentIds) {
                        if (!array_key_exists($contentType, $relatedIds)) {
                            $relatedIds[$contentType] = [];
                        }
                        $relatedIds[$contentType] = array_merge($relatedIds[$contentType], $contentIds);
                    }
                }
            }
            foreach ($relatedIds as $contentType => $contentIds) {
                $relatedIds[$contentType] = array_unique($contentIds);
            }
        } catch (\Exception $e) {
            $this->getLogger()->error('Failed to build related ids array for audit log: ' . $e->getMessage());
        }
        try {
            $this->apiWrapper->createAuditLogRecord($profile, CreateRecordParameters::ACTION_TYPE_UPLOAD, $request->getDescription(), [
                'relatedContentIds' => $relatedIds,
                'sourceBlogId' => $curBlogId,
                'sourceId' => $request->getContentId(),
                'targetBlogIds' => $request->getTargetBlogIds(),
            ], $jobInfo, $job->isAuthorize());
        } catch (\Exception $e) {
            $this->getLogger()->error(sprintf(
                'Failed to create audit log record actionType=%s, requestDescription="%s", sourceId=%d, sourceBlogId=%d, errorMessage="%s"',
                CreateRecordParameters::ACTION_TYPE_UPLOAD,
                $request->getDescription(),
                $request->getContentId(),
                $curBlogId,
                addslashes($e->getMessage()),
            ));
        }

        foreach ($request->getTargetBlogIds() as $targetBlogId) {
            $submissionTemplateArray = [
                SubmissionEntity::FIELD_SOURCE_BLOG_ID => $curBlogId,
                SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlogId,
            ];

            /**
             * Submission for original content may already exist
             */
            $searchParams = array_merge($submissionTemplateArray, [
                SubmissionEntity::FIELD_CONTENT_TYPE => $request->getContentType(),
                SubmissionEntity::FIELD_SOURCE_ID => $request->getContentId(),
            ]);

            $sources = $this->getSources($request, $targetBlogId);

            $result = $this->submissionManager->find($searchParams, 1);

            if (empty($result)) {
                $sources[] = [
                    'id' => $request->getContentId(),
                    'type' => $request->getContentType(),
                ];
            } else {
                $submission = ArrayHelper::first($result);
                $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_NEW);
                $this->storeWithJobInfo($submission, $jobInfo, $request->getDescription());
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

                $submission = $this->submissionFactory->fromArray($submissionArray);
                $title = $this->getTitle($submission);
                if ($title !== '') {
                    $submission->setSourceTitle($title);
                }

                try {
                    $submission->generateFileUri();
                    $this->storeWithJobInfo($submission, $jobInfo, $request->getDescription());
                } catch (SmartlingInvalidFactoryArgumentException) {
                    $this->getLogger()->info("Skipping submission because no mapper was found: contentType={$submission->getContentType()} sourceBlogId={$submission->getSourceBlogId()}, sourceId={$submission->getSourceId()}, targetBlogId={$submission->getTargetBlogId()}");
                }
            }
        }
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

    public function getBackwardRelatedTaxonomies(int $contentId, string $contentType): array
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
     * @see do_shortcode_tag(), shortcode_parse_atts() attributes might be an array, or an empty string
     * @see extractFieldsFromShortcodes();
     * @noinspection PhpUnused
     */
    public function shortcodeHandler(mixed $attributes, string $content, string $shortcodeName): void
    {
        if (!is_array($attributes)) {
            return;
        }
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

    private function extractFieldsFromGutenbergBlocks(string $basename, string $string): array
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

    /**
     * @return int[]
     */
    private function getPostContentReferences(string $content): array
    {
        $result = [];
        try {
            foreach ($this->gutenbergBlockHelper->parseBlocks($content) as $block) {
                $result = $this->addPostContentReferences($result, $block);
            }
        } catch (SmartlingGutenbergParserNotFoundException $e) {
            $this->getLogger()->warning('Failed to parse blocks: ' . $e->getMessage());
            return $result;
        }

        $result = array_unique($result);

        return [self::POST_BASED_PROCESSOR => array_combine($result, $result)] ;
    }

    /**
     * @return int[]
     * @noinspection JsonEncodingApiUsageInspection
     */
    private function addPostContentReferences(array $array, GutenbergBlock $block): array
    {
        $this->getLogger()->debug('Detecting post content references in block ' . $block);
        $referencesFromAcf = $this->getReferencesFromAcf($block);
        $this->getLogger()->debug(sprintf("Got %d references from ACF", count($referencesFromAcf)));
        $referencesFromSetupBlocks = $this->getReferencesFromGutenbergReplacementRules($block);
        foreach ($block->getInnerBlocks() as $innerBlock) {
            $array = $this->addPostContentReferences($array, $innerBlock);
        }

        $this->getLogger()->debug("addPostContentReferences({$block->serializeBlock($block)}");
        $this->getLogger()->debug("Add post content references result: " . json_encode($array));
        $this->getLogger()->debug("Add post content references ACF: " . json_encode($referencesFromAcf));
        $this->getLogger()->debug("Add post content references from setup: " . json_encode($referencesFromSetupBlocks));
        return array_merge($array, $referencesFromAcf, $referencesFromSetupBlocks);
    }

    /**
     * @param int[] $targetBlogIds
     * @noinspection JsonEncodingApiUsageInspection
     */
    public function getRelations(string $contentType, int $id, array $targetBlogIds): DetectedRelations
    {
        $detectedReferences = ['attachment' => []];
        $curBlogId = $this->contentHelper->getSiteHelper()->getCurrentBlogId();
        $this->getLogger()->debug("Getting relations for contentType=\"$contentType\", id=$id, blogId=$curBlogId");

        if (!$this->contentHelper->checkEntityExists($curBlogId, $contentType, $id)) {
            throw new SmartlingHumanReadableException('Requested content is not found', 'content.not.found', 404);
        }

        $entity = $this->contentHelper->getIoFactory()->getMapper($contentType)->get($id);

        $content = [
            'entity' => $entity->toArray(),
            'meta' => [],
        ];
        if ($entity instanceof EntityWithMetadata) {
            $content['meta'] = $entity->getMetadata();
        }

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
                    $this->fieldFilterHelper->flattenArray($this->extractFieldsFromGutenbergBlocks($fName, $fValue))
                );
            }
            $fields = array_merge($fields, $extraFields);
        } catch (Exception $e) {
            $this->getLogger()->info("Gutenberg not detected, skipping search for references in Gutenberg blocks for request contentType=\"$contentType\", contentId=\"$id\"");
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

                    $detectedReferences[$shortProcessorName][] = (int)$fValue;
                } else {
                    $detectedReferences['attachment'] = array_merge($detectedReferences['attachment'],
                        $this->absoluteLinkedAttachmentCoreHelper->getImagesIdsFromString($fValue, $curBlogId));
                    $detectedReferences['attachment'] = array_unique($detectedReferences['attachment']);
                }
            } catch (Exception $e) {
                $this->getLogger()->warning(sprintf(
                    'Failed searching for processor for fieldName="%s", fieldValue="%s", errorMessage="%s"',
                    $fName,
                    $fValue,
                    $e->getMessage(),
                ));
            }
        }

        $detectedReferences['taxonomies'] = $this->getBackwardRelatedTaxonomies($id, $contentType);

        $this->getLogger()->debug('References before getting post content references: ' . json_encode($detectedReferences));
        if (array_key_exists('post_content', $content['entity'])) {
            $detectedReferences = array_merge_recursive($detectedReferences, $this->getPostContentReferences($content['entity']['post_content']));
            $this->getLogger()->debug('References after getting post content references: ' . json_encode($detectedReferences));
        }
        $detectedReferences = array_merge_recursive($this->externalContentManager->getExternalRelations($contentType, $id), $detectedReferences);
        $this->getLogger()->debug('References after getting external relations: ' . json_encode($detectedReferences));

        $count = 0;
        if (array_key_exists(self::POST_BASED_PROCESSOR, $detectedReferences)) {
            $count = count($detectedReferences[self::POST_BASED_PROCESSOR], COUNT_RECURSIVE);
        }
        $this->getLogger()->debug(sprintf(self::POST_BASED_PROCESSOR . ' has %d references', $count));
        $detectedReferences = $this->normalizeReferences($detectedReferences);
        $this->getLogger()->debug('References after normalizing: ' . json_encode($detectedReferences));

        $responseData = new DetectedRelations($detectedReferences);

        foreach ($targetBlogIds as $targetBlogId) {
            foreach ($detectedReferences as $detectedContentType => $ids) {
                if (in_array($detectedContentType, array_merge($this->contentTypeManager->getRegisteredContentTypes(), $this->externalContentManager->getExternalContentTypes()), true)) {
                    foreach ($ids as $detectedId) {
                        if (!$this->submissionManager->submissionExistsNoLastError($detectedContentType, $curBlogId, $detectedId, $targetBlogId)) {
                            $responseData->addMissingReference($targetBlogId, $detectedContentType, $detectedId);
                        } else {
                            $this->getLogger()->debug("Skipped adding relatedId=$detectedId for sourceContentId=$id, blogId=$targetBlogId: submission exists");
                        }
                    }
                } else {
                    $this->getLogger()->debug("Excluded $detectedContentType from related submissions, type not in registered or external types");
                }
            }
        }

        return $responseData;
    }

    public function normalizeReferences(array $references): array
    {
        $result = [];

        if (isset($references['attachment'])) {
            $result['attachment'] = $references['attachment'];
        }

        if (isset($references['MediaBasedProcessor'])) {
            $result['attachment'] = array_merge(($result['attachment'] ?? []), $references['MediaBasedProcessor']);
        }

        if (isset($references[self::POST_BASED_PROCESSOR])) {
            foreach ($references[self::POST_BASED_PROCESSOR] as $postId) {
                $postType = $this->wordpressProxy->get_post_type($postId);
                if ($postType !== false) {
                    $result[$postType][] = $postId;
                } else {
                    $this->getLogger()->warning("WordPress returned no post exist for detected reference postId=$postId");
                }
            }
        }

        if (isset($references['TermBasedProcessor'])) {
            $termTypeIds = $references['TermBasedProcessor'];
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

        foreach ($this->externalContentManager->getExternalContentTypes() as $externalContentType) {
            if (array_key_exists($externalContentType, $references)) {
                $result[$externalContentType] = $references[$externalContentType];
            }
        }

        foreach ($result as $contentType => $items) {
            $result[$contentType] = array_unique($items);
            if (empty($result[$contentType])) {
                unset($result[$contentType]);
            }
        }

        return $result;
    }

    private function storeWithJobInfo(SubmissionEntity $submission, JobEntityWithBatchUid $jobInfo, string $description): void
    {
        $submission->setBatchUid($jobInfo->getBatchUid());
        $submission->setJobInfo($jobInfo->getJobInformationEntity());
        $this->submissionManager->storeEntity($submission);
        $this->logSubmissionCreated($submission, $description);
    }

    public function getTitle(SubmissionEntity $submission): string
    {
        try {
            return $this->contentHelper->readSourceContent($submission)->getTitle();
        } catch (\Exception $e) {
            $this->getLogger()->notice(sprintf('Unable to get content title for submissionId=%d, sourceBlogId=%d, sourceId=%d, type="%s"', $submission->getId(), $submission->getSourceBlogId(), $submission->getSourceId(), $submission->getContentType()));
            $this->getLogger()->debug('Exception: ' . $e->getMessage());
            return '';
        }
    }

    private function getSources(UserCloneRequest $request, int $targetBlogId): array
    {
        $sources = [];

        foreach ($request->getRelationsOrdered() as $relationSet) {
            foreach ($relationSet[$targetBlogId] as $type => $ids) {
                foreach ($ids as $id) {
                    if ($id === $request->getContentId() && $type === $request->getContentType()) {
                        $this->getLogger()->info("Related list contains reference to root content, skip adding sourceId=$id, contentType=$type to sources list");
                    } else {
                        $sources[] = [
                            'id' => $id,
                            'type' => $type,
                        ];
                    }
                }
            }
        }

        return $sources;
    }

    private function logSubmissionCreated(SubmissionEntity $submission, string $description): void
    {
        $this->getLogger()->info(sprintf("Adding submissionId=%d, fileName=%s, sourceBlogId=%d, sourceId=%d for upload to batchUid=%s due to user request, reason=\"%s\"", $submission->getId(), $submission->getFileUri(), $submission->getSourceBlogId(), $submission->getSourceId(), $submission->getBatchUid(), $description));
    }

    /**
     * @return int[]
     */
    public function getReferencesFromAcf(GutenbergBlock $block): array
    {
        $result = [];
        if ($block->getBlockName() === null || !str_starts_with($block->getBlockName(), 'acf/')) {
            return $result;
        }

        foreach ($block->getAttributes()['data'] ?? [] as $attribute => $value) {
            if (!str_starts_with($attribute, '_')) {
                continue;
            }
            $type = $this->acfDynamicSupport->getReferencedTypeByKey($value);
            if (in_array($type, [
                AcfDynamicSupport::REFERENCED_TYPE_MEDIA,
                AcfDynamicSupport::REFERENCED_TYPE_POST,
            ], true)) {
                $referencedValue = $block->getAttributes()['data'][substr($attribute, 1)] ?? null;
                if (is_numeric($referencedValue)) {
                    $result[] = (int)$referencedValue;
                }
            }
        }
        return $result;
    }

    /**
     * @return int[]
     */
    public function getReferencesFromGutenbergReplacementRules(GutenbergBlock $block): array
    {
        $result = [];
        foreach ($this->mediaAttachmentRulesManager->getGutenbergReplacementRules($block->getBlockName(), $block->getAttributes()) as $rule) {
            try {
                $replacer = $this->replacerFactory->getReplacer($rule->getReplacerId());
                $this->getLogger()->debug("Got replacerId={$rule->getReplacerId()}");
            } catch (EntityNotFoundException) {
                continue;
            }
            $value = $this->gutenbergBlockHelper->getAttributeValue($block, $rule);
            if ($replacer instanceof ContentIdReplacer && is_numeric($value)) {
                $result[] = (int)$value;
                $this->getLogger()->debug("Added relatedId=$value to references (found rule for blockName=\"{$rule->getBlockType()} rule=\"{$rule->getPropertyPath()}");
            }
        }

        return $result;
    }
}
