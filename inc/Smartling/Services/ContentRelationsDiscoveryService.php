<?php

namespace Smartling\Services;

use Exception;
use Psr\Log\LoggerInterface;
use Smartling\ApiWrapper;
use Smartling\DbAl\LocalizationPluginProxyInterface;
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
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

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

    /**
     * Action name
     */
    const ACTION_NAME = 'smartling-get-relations';

    const ACTION_NAME_CREATE_SUBMISSIONS = 'smartling-create-submissions';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ContentHelper
     */
    private $contentHelper;

    /**
     * @var FieldsFilterHelper
     */
    private $fieldFilterHelper;

    /**
     * @var MetaFieldProcessorManager
     */
    private $metaFieldProcessorManager;

    /**
     * @var AbsoluteLinkedAttachmentCoreHelper
     */
    private $absoluteLinkedAttachmentCoreHelper;

    /**
     * @var ShortcodeHelper
     */
    private $shortcodeHelper;

    /**
     * @var GutenbergBlockHelper
     */
    private $gutenbergBlockHelper;

    /**
     * @var SubmissionManager
     */
    private $submissionManager;

    /**
     * @var ApiWrapper
     */
    private $apiWrapper;

    /**
     * @var SettingsManager
     */
    private $settingsManager;
	private $localizationPluginProxy;

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        if (!($this->logger instanceof LoggerInterface)) {
            $this->logger = MonologWrapper::getLogger(get_class($this));
        }
        return $this->logger;
    }

    /**
     * @return ContentHelper
     */
    public function getContentHelper()
    {
        return $this->contentHelper;
    }

    /**
     * @param ContentHelper $contentHelper
     */
    public function setContentHelper($contentHelper)
    {
        $this->contentHelper = $contentHelper;
    }

    /**
     * @return FieldsFilterHelper
     */
    public function getFieldFilterHelper()
    {
        return $this->fieldFilterHelper;
    }

    /**
     * @param FieldsFilterHelper $fieldFilterHelper
     */
    public function setFieldFilterHelper($fieldFilterHelper)
    {
        $this->fieldFilterHelper = $fieldFilterHelper;
    }

    /**
     * @return MetaFieldProcessorManager
     */
    public function getMetaFieldProcessorManager()
    {
        return $this->metaFieldProcessorManager;
    }

    /**
     * @param MetaFieldProcessorManager $metaFieldProcessorManager
     */
    public function setMetaFieldProcessorManager($metaFieldProcessorManager)
    {
        $this->metaFieldProcessorManager = $metaFieldProcessorManager;
    }

    /**
     * @return AbsoluteLinkedAttachmentCoreHelper
     */
    public function getAbsoluteLinkedAttachmentCoreHelper()
    {
        return $this->absoluteLinkedAttachmentCoreHelper;
    }

    /**
     * @param AbsoluteLinkedAttachmentCoreHelper $absoluteLinkedAttachmentCoreHelper
     */
    public function setAbsoluteLinkedAttachmentCoreHelper($absoluteLinkedAttachmentCoreHelper)
    {
        $this->absoluteLinkedAttachmentCoreHelper = $absoluteLinkedAttachmentCoreHelper;
    }

    /**
     * @return ShortcodeHelper
     */
    public function getShortcodeHelper()
    {
        return $this->shortcodeHelper;
    }

    /**
     * @param ShortcodeHelper $shortcodeHelper
     */
    public function setShortcodeHelper($shortcodeHelper)
    {
        $this->shortcodeHelper = $shortcodeHelper;
    }

    /**
     * @return GutenbergBlockHelper
     */
    public function getGutenbergBlockHelper()
    {
        return $this->gutenbergBlockHelper;
    }

    /**
     * @param GutenbergBlockHelper $gutenbergBlockHelper
     */
    public function setGutenbergBlockHelper($gutenbergBlockHelper)
    {
        $this->gutenbergBlockHelper = $gutenbergBlockHelper;
    }

    /**
     * @return SubmissionManager
     */
    public function getSubmissionManager()
    {
        return $this->submissionManager;
    }

    /**
     * @param SubmissionManager $submissionManager
     */
    public function setSubmissionManager($submissionManager)
    {
        $this->submissionManager = $submissionManager;
    }

    /**
     * @return ApiWrapper
     */
    public function getApiWrapper()
    {
        return $this->apiWrapper;
    }

    /**
     * @param ApiWrapper $apiWrapper
     */
    public function setApiWrapper($apiWrapper)
    {
        $this->apiWrapper = $apiWrapper;
    }

    /**
     * @return SettingsManager
     */
    public function getSettingsManager()
    {
        return $this->settingsManager;
    }

    /**
     * @param SettingsManager $settingsManager
     */
    public function setSettingsManager($settingsManager)
    {
        $this->settingsManager = $settingsManager;
    }

    /**
     * ContentRelationDiscoveryService constructor.
     * @param ContentHelper                      $contentHelper
     * @param FieldsFilterHelper                 $fieldFilterHelper
     * @param MetaFieldProcessorManager          $fieldProcessorManager
	 * @param LocalizationPluginProxyInterface $localizationPluginProxy
     * @param AbsoluteLinkedAttachmentCoreHelper $absoluteLinkedAttachmentCoreHelper
     * @param ShortcodeHelper                    $shortcodeHelper
     * @param GutenbergBlockHelper               $blockHelper
     * @param SubmissionManager                  $submissionManager
     * @param ApiWrapper                         $apiWrapper
     * @param SettingsManager                    $settingsManager
     */
    public function __construct(
        ContentHelper $contentHelper,
        FieldsFilterHelper $fieldFilterHelper,
        MetaFieldProcessorManager $fieldProcessorManager,
		LocalizationPluginProxyInterface $localizationPluginProxy,
        AbsoluteLinkedAttachmentCoreHelper $absoluteLinkedAttachmentCoreHelper,
        ShortcodeHelper $shortcodeHelper,
        GutenbergBlockHelper $blockHelper,
        SubmissionManager $submissionManager,
        ApiWrapper $apiWrapper,
        SettingsManager $settingsManager
    ) {
        $this->setContentHelper($contentHelper);
        $this->setFieldFilterHelper($fieldFilterHelper);
        $this->setMetaFieldProcessorManager($fieldProcessorManager);
		$this->localizationPluginProxy = $localizationPluginProxy;
        $this->setAbsoluteLinkedAttachmentCoreHelper($absoluteLinkedAttachmentCoreHelper);
        $this->setShortcodeHelper($shortcodeHelper);
        $this->setGutenbergBlockHelper($blockHelper);
        $this->setSubmissionManager($submissionManager);
        $this->setApiWrapper($apiWrapper);
        $this->setSettingsManager($settingsManager);
    }

    public function register()
    {
        parent::register();
        add_action('wp_ajax_' . static::ACTION_NAME_CREATE_SUBMISSIONS, [$this, 'createSubmissionsHandler']);
    }

    /**
     * @param string $sourceBlogId
     * @param array  $job
     * @return string
     * @throws \Smartling\Exception\SmartlingConfigException
     * @throws \Smartling\Exception\SmartlingDbException
     * @throws \Smartling\Exceptions\SmartlingApiException
     */
    protected function getBatchUid($sourceBlogId, array $job)
    {
        return $this
            ->getApiWrapper()
            ->retrieveBatch(
                $this->getSettingsManager()->getSingleSettingsProfile($sourceBlogId),
                $job['id'],
                'true' === $job['authorize'],
                [
                    'name'        => $job['name'],
                    'description' => $job['description'],
                    'dueDate'     => [
                        'date'     => $job['dueDate'],
                        'timezone' => $job['timeZone'],
                    ],
                ]
            );
    }

	/**
	 * @param string $batchUid
	 * @param array $contentIds
	 * @param string $contentType
	 * @param int $currentBlogId
	 * @param array $targetBlogIds
	 */
	public function bulkUploadHandler( $batchUid, array $contentIds, $contentType, $currentBlogId, array $targetBlogIds ) {
		foreach ( $targetBlogIds as $targetBlogId ) {
			$blogFields = [
				SubmissionEntity::FIELD_SOURCE_BLOG_ID => $currentBlogId,
				SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlogId,
			];
			foreach ( $contentIds as $id ) {
				$existing = $this->getSubmissionManager()->find( array_merge( $blogFields, [
					SubmissionEntity::FIELD_CONTENT_TYPE => $contentType,
					SubmissionEntity::FIELD_SOURCE_ID    => $id
				] ) );

				if ( empty( $existing ) ) {
					$submission = $this->getSubmissionManager()->getSubmissionEntity( $contentType, $currentBlogId, $id, $targetBlogId, $this->localizationPluginProxy );
				} else {
					/** @var SubmissionEntity $submission */
					$submission = ArrayHelper::first( $existing );
				}
				$submission->setBatchUid( $batchUid );
				$submission->setStatus( SubmissionEntity::SUBMISSION_STATUS_NEW );
				$submission->getFileUri();
				$this->getSubmissionManager()->storeEntity( $submission );
			}
		}
		$this->returnResponse( [ 'status' => 'SUCCESS' ] );
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
     * @return void
     */
    public function createSubmissionsHandler($data = '')
    {
		if (!is_array($data)) {
            $data = $_POST;
        }
        try {
			$contentType = $data['source']['contentType'];
            $curBlogId = $this->getContentHelper()->getSiteHelper()->getCurrentBlogId();
            $batchUid  = $this->getBatchUid($curBlogId, $data['job']);

            $targetBlogIds = explode(',', $data['targetBlogIds']);

			if (array_key_exists( 'ids', $data)) {
				return $this->bulkUploadHandler( $batchUid, $data['ids'], $contentType, $curBlogId, $targetBlogIds );
			}

            foreach ($targetBlogIds as $targetBlogId) {
                $submissionTemplateArray = [
                    SubmissionEntity::FIELD_SOURCE_BLOG_ID => $this->getContentHelper()->getSiteHelper()->getCurrentBlogId(),
                    SubmissionEntity::FIELD_TARGET_BLOG_ID => (int)$targetBlogId,
                ];

                /**
                 * Submission for original content may already exist
                 */
                $searchParams = array_merge($submissionTemplateArray, [
					SubmissionEntity::FIELD_CONTENT_TYPE => $contentType,
                    SubmissionEntity::FIELD_SOURCE_ID    => ArrayHelper::first($data['source']['id']),
                ]);

                $sources = [];

                if (array_key_exists($targetBlogId, $data['relations'])) {
                    foreach ($data['relations'][$targetBlogId] as $sysType => $ids) {
                        foreach ($ids as $id) {
                            $sources[] = [
                                'type' => $sysType,
                                'id'   => $id,
                            ];
                        }
                    }
                }

                $result = $this->getSubmissionManager()->find($searchParams, 1);

                if (empty($result)) {
                    $sources[] = [
						'type' => $contentType,
                        'id'   => ArrayHelper::first($data['source']['id']),
                    ];
                } else {
                    /**
                     * @var SubmissionEntity $submission
                     */
                    $submission = ArrayHelper::first($result);
                    $submission->setBatchUid($batchUid);
                    $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_NEW);
                    $this->getSubmissionManager()->storeEntity($submission);
                }

                /**
                 * Adding fields to template
                 */
                $submissionTemplateArray = array_merge($submissionTemplateArray, [
                    SubmissionEntity::FIELD_STATUS    => SubmissionEntity::SUBMISSION_STATUS_NEW,
                    SubmissionEntity::FIELD_BATCH_UID => $batchUid,
                    SubmissionEntity::FIELD_SUBMISSION_DATE => DateTimeHelper::nowAsString(),
                ]);

                foreach ($sources as $source) {
                    $submissionArray = array_merge($submissionTemplateArray, [
                        SubmissionEntity::FIELD_CONTENT_TYPE => $source['type'],
                        SubmissionEntity::FIELD_SOURCE_ID    => $source['id'],
                    ]);

                    $submission = SubmissionEntity::fromArray($submissionArray, $this->getLogger());

                    // trigger generation of fileUri
                    $submission->getFileUri();
                    $this->getSubmissionManager()->storeEntity($submission);
                }
            }

            $this->returnResponse(['status' => 'SUCCESS']);
        } catch (Exception $e) {
            $this->returnError('content.submission.failed', $e->getMessage());
        }
    }

    public function getRequestSource()
    {
        return $_GET;
    }

    public function getContentType()
    {
        return $this->getRequiredParam('content-type');
    }

    public function getId()
    {
        return (int)$this->getRequiredParam('id');
    }

    public function getTargetBlogIds()
    {
        $blogs = explode(',', $this->getRequiredParam('targetBlogIds'));

        array_walk($blogs, function ($el) {
            return (int)$el;
        });

        $blogs = array_unique($blogs);

        return $blogs;
    }

    private function getTaxonomiesForContentType($contentType)
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

    private function getBackwardRelatedTaxonomies($contentId, $contentType)
    {
        $detectedReferences   = [];
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

    private $shortcodeFields = [];

    /**
     * @param array  $attributes
     * @param string $content
     * @param string $shortcodeName
     */
    public function shortcodeHandler(array $attributes, $content, $shortcodeName)
    {
        foreach ($attributes as $attributeName => $attributeValue) {
            $this->shortcodeFields[$shortcodeName . '/' . $attributeName][] = $attributeValue;
            if (!StringHelper::isNullOrEmpty($content)) {
                $this->getShortcodeHelper()->renderString($content);
            }
        }
    }

    /**
     * @param string $baseName
     * @param string $string
     * @return array
     */
    private function extractFieldsFromShortcodes($baseName, $string)
    {
        $detectedShortcodes = $this->getShortcodeHelper()->getRegisteredShortcodes();
        $this->getShortcodeHelper()->replaceShortcodeHandler($detectedShortcodes, 'shortcodeHandler', $this);
        $this->getShortcodeHelper()->renderString($string);
        $this->getShortcodeHelper()->restoreHandlers();
        $fields = [];
        foreach ($this->shortcodeFields as $fName => $fValue) {
            $fields[$baseName . '/' . $fName] = $fValue;
        }

        $this->shortcodeFields = [];
        return $fields;
    }

    private function extractFieldsFromGutenbergBlock($basename, $string)
    {
        $fields = [];
        $blocks = $this->getGutenbergBlockHelper()->parseBlocks($string);
        foreach ($blocks as $block) {
            $pointer       = 0;
            $blockNamePart = $basename . '/' . $block['blockName'];
            $_fields       = $block['attrs'];

            /**
             * Extract regular attributes
             */
            foreach ($_fields as $fName => $fValue) {
                $fields[$blockNamePart . '/' . $fName] = $fValue;
            }

            /**
             * get nested content attributes
             */
            foreach ($block['innerContent'] as $chunk) {
                if (!is_string($chunk)) {
                    $chunkFields = $this->extractFieldsFromGutenbergBlock($blockNamePart,
                        $block['innerBlocks'][$pointer++]);
                    $fields      = array_merge($fields, $chunkFields);
                }
            }
        }
        return $fields;
    }

    public function actionHandler()
    {
        $contentType   = $this->getContentType();
        $id            = $this->getId();
        $curBlogId     = $this->getContentHelper()->getSiteHelper()->getCurrentBlogId();
        $targetBlogIds = $this->getTargetBlogIds();

        if ($this->getContentHelper()->checkEntityExists($curBlogId, $contentType, $id)) {

            $ioWrapper = $this->getContentHelper()->getIoFactory()->getMapper($contentType);

            $content = [
                'entity' => $ioWrapper->get($id)->toArray(),
                'meta'   => $ioWrapper->get($id)->getMetadata(),
            ];

            $fields = $this->getFieldFilterHelper()->flatternArray($content);

            /**
             * adding fields from shortcodes
             */
            $extraFields = [];
            foreach ($fields as $fName => $fValue) {
                $extraFields = array_merge($extraFields,
                    $this->getFieldFilterHelper()->flatternArray($this->extractFieldsFromShortcodes($fName, $fValue)));
            }
            $fields = array_merge($fields, $extraFields);

            try {
                /**
                 * check if gutenberg exists
                 */
                $this->getGutenbergBlockHelper()->loadExternalDependencies();

                /**
                 * adding fields from blocks
                 */
                $extraFields = [];
                foreach ($fields as $fName => $fValue) {
                    $extraFields = array_merge($extraFields,
                        $this->getFieldFilterHelper()->flatternArray($this->extractFieldsFromGutenbergBlock($fName,
                            $fValue)));
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

            $detectedReferences = ['attachment' => []];

            foreach ($fields as $fName => $fValue) {
                try {
                    $this->getLogger()->debug(vsprintf('Looking for processor for field \'%s\'', [$fName]));
                    $processor = $this->getMetaFieldProcessorManager()->getProcessor($fName);
                    $this->getLogger()->debug(vsprintf('Detected processor \'%s\' for field \'%s\'',
                        [get_class($processor), $fName]));
                    /**
                     * in case that we found simple default processor try to treat as ACF field
                     */
                    if ($processor instanceof DefaultMetaFieldProcessor) {
                        $this->getLogger()->debug(vsprintf('Trying to treat \'%s\' field as ACF', [$fName]));
                        $processor = $this
                            ->getMetaFieldProcessorManager()
                            ->getAcfTypeDetector()
                            ->getProcessorByMetaFields($fName, $content['meta']);
                        if ($processor === false) {
                            $parts = explode('/', $fName);
                            $lastPart = end($parts);
                            if ($lastPart !== false && strpos($lastPart, '_') !== 0) {
                                $parts[count($parts) - 1] = "_$lastPart";
                                $field = implode('/', $parts);
                                if (array_key_exists($field, $fields)) {
                                    $processor = $this->getMetaFieldProcessorManager()
                                        ->getAcfTypeDetector()->getAcfProcessor($fName, $fields[$field]);
                                }
                            }

                        }
                    }

                    /**
                     * If processor is detected
                     */
                    if ($processor instanceof MetaFieldProcessorAbstract && 0 !== (int)$fValue) {
                        $shortProcessorName = ArrayHelper::last(explode('\\', get_class($processor)));

                        $detectedReferences[$shortProcessorName][$fValue][] = $fName;
                    } else {
                        if (!isset($detectedReferences['attachment'])) {
                            $detectedReferences['attachment'] = [];
                        }
                        $detectedReferences['attachment'] = array_merge($detectedReferences['attachment'],
                            $this->getAbsoluteLinkedAttachmentCoreHelper()->getImagesIdsFromString($fValue,
                                $curBlogId));
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

            $detectedReferences = $this->normalizeReferences($detectedReferences);

            $responseData = [
                'originalReferences' => $detectedReferences,
            ];

            foreach ($targetBlogIds as $targetBlogId) {
                foreach ($detectedReferences as $contentType => $ids) {
                    foreach ($ids as $id) {
                        if (!$this->submissionExists($contentType, $curBlogId, $id, $targetBlogId)) {
                            $responseData['missingTranslatedReferences'][$targetBlogId][$contentType][] = $id;
                        }
                    }
                }
            }

            $this->returnSuccess(
                [
                    'data' => $responseData,
                ]
            );

        } else {
            $this->returnError('content.not.found', 'Requested content is not found', 404);
        }
    }

    /**
     * @param string $contentType
     * @param int    $sourceBlogId
     * @param int    $contentId
     * @param int    $targetBlogId
     * @return bool
     */
    protected function submissionExists($contentType, $sourceBlogId, $contentId, $targetBlogId)
    {
        return 1 === count($this->getSubmissionManager()->find([
                SubmissionEntity::FIELD_SOURCE_BLOG_ID => $sourceBlogId,
                SubmissionEntity::FIELD_CONTENT_TYPE   => $contentType,
                SubmissionEntity::FIELD_SOURCE_ID      => $contentId,
                SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlogId,
            ], 1));
    }

    protected function normalizeReferences(array $references)
    {
        $result = [];

        if (isset($references['attachment'])) {
            $result['attachment'] = $references['attachment'];
        }

        if (isset($references['MediaBasedProcessor'])) {
            $result['attachment'] = array_merge((isset($result['attachment']) ? $result['attachment'] : []),
                array_keys($references['MediaBasedProcessor']));
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
