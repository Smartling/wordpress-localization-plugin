<?php

namespace Smartling\Services;

use Psr\Log\LoggerInterface;
use Smartling\Helpers\AbsoluteLinkedAttachmentCoreHelper;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Helpers\MetaFieldProcessor\DefaultMetaFieldProcessor;
use Smartling\Helpers\MetaFieldProcessor\MetaFieldProcessorAbstract;
use Smartling\Helpers\MetaFieldProcessor\MetaFieldProcessorManager;
use Smartling\MonologWrapper\MonologWrapper;

/**
 *
 * ajax service that discovers related items.
 * usage: GET /wp-admin/admin-ajax.php?action=smartling-get-relations&content-type=post&id=48
 *
 * blogId is discovered from current active blog via Wordpress Multisite API
 *
 * Class ContentRelationDiscoveryService
 * @package Smartling\Services
 */
class ContentRelationDiscoveryService extends BaseAjaxServiceAbstract
{

    /**
     * Action name
     */
    const ACTION_NAME = 'smartling-get-relations';

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
     * ContentRelationDiscoveryService constructor.
     * @param ContentHelper                      $contentHelper
     * @param FieldsFilterHelper                 $fieldFilterHelper
     * @param MetaFieldProcessorManager          $fieldProcessorManager
     * @param AbsoluteLinkedAttachmentCoreHelper $absoluteLinkedAttachmentCoreHelper
     */
    public function __construct(
        ContentHelper $contentHelper,
        FieldsFilterHelper $fieldFilterHelper,
        MetaFieldProcessorManager $fieldProcessorManager,
        AbsoluteLinkedAttachmentCoreHelper $absoluteLinkedAttachmentCoreHelper
    ) {
        $this->setContentHelper($contentHelper);
        $this->setFieldFilterHelper($fieldFilterHelper);
        $this->setMetaFieldProcessorManager($fieldProcessorManager);
        $this->setAbsoluteLinkedAttachmentCoreHelper($absoluteLinkedAttachmentCoreHelper);
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

    public function actionHandler()
    {
        $contentType = $this->getContentType();
        $id          = $this->getId();
        $curBlogId   = $this->getContentHelper()->getSiteHelper()->getCurrentBlogId();

        if ($this->getContentHelper()->checkEntityExists($curBlogId, $contentType, $id)) {

            $ioWrapper = $this->getContentHelper()->getIoFactory()->getMapper($contentType);

            $content = [
                'entity' => $ioWrapper->get($id)->toArray(),
                'meta'   => $ioWrapper->get($id)->getMetadata(),
            ];

            $fields = $this->getFieldFilterHelper()->flatternArray($content);

            $detectedReferences = [];

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
                        $this->getLogger()->debug(vsprintf('Trying to threat \'%s\' field as ACF', [$fName]));
                        $processor = $this
                            ->getMetaFieldProcessorManager()
                            ->getAcfTypeDetector()
                            ->getProcessorByMetaFields($fName, $content['meta']);
                    }

                    /**
                     * If processor is detected
                     */
                    if ($processor instanceof MetaFieldProcessorAbstract && 0 !== (int)$fValue) {
                        $shortProcessorName = ArrayHelper::last(explode('\\', get_class($processor)));

                        $detectedReferences[$shortProcessorName][$fValue][] = $fName;
                    } else {
                        if (!isset($detectedReferences['images'])) {
                            $detectedReferences['images'] = [];
                        }
                        $detectedReferences['images'] = array_merge($detectedReferences['images'],
                            $this->getAbsoluteLinkedAttachmentCoreHelper()->getImagesIdsFromString($fValue,
                                $curBlogId));
                        $detectedReferences['images']=array_unique($detectedReferences['images']);
                    }
                } catch (\Exception $e) {
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

            $this->returnSuccess(
                [
                    'items' => [
                        $detectedReferences,
                    ],
                ]
            );

        } else {
            $this->returnError('content.not.found', 'Requested content is not found', 404);
        }
    }
}
