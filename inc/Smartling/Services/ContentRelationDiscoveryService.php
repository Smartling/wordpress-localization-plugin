<?php

namespace Smartling\Services;

use Psr\Log\LoggerInterface;
use Smartling\Helpers\AbsoluteLinkedAttachmentCoreHelper;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Helpers\GutenbergBlockHelper;
use Smartling\Helpers\MetaFieldProcessor\DefaultMetaFieldProcessor;
use Smartling\Helpers\MetaFieldProcessor\MetaFieldProcessorAbstract;
use Smartling\Helpers\MetaFieldProcessor\MetaFieldProcessorManager;
use Smartling\Helpers\ShortcodeHelper;
use Smartling\Helpers\StringHelper;
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
     * @var ShortcodeHelper
     */
    private $shortcodeHelper;

    /**
     * @var GutenbergBlockHelper
     */
    private $gutenbergBlockHelper;

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
     * ContentRelationDiscoveryService constructor.
     * @param ContentHelper                      $contentHelper
     * @param FieldsFilterHelper                 $fieldFilterHelper
     * @param MetaFieldProcessorManager          $fieldProcessorManager
     * @param AbsoluteLinkedAttachmentCoreHelper $absoluteLinkedAttachmentCoreHelper
     * @param ShortcodeHelper                    $shortcodeHelper
     * @param GutenbergBlockHelper               $blockHelper
     */
    public function __construct(
        ContentHelper $contentHelper,
        FieldsFilterHelper $fieldFilterHelper,
        MetaFieldProcessorManager $fieldProcessorManager,
        AbsoluteLinkedAttachmentCoreHelper $absoluteLinkedAttachmentCoreHelper,
        ShortcodeHelper $shortcodeHelper,
        GutenbergBlockHelper $blockHelper
    ) {
        $this->setContentHelper($contentHelper);
        $this->setFieldFilterHelper($fieldFilterHelper);
        $this->setMetaFieldProcessorManager($fieldProcessorManager);
        $this->setAbsoluteLinkedAttachmentCoreHelper($absoluteLinkedAttachmentCoreHelper);
        $this->setShortcodeHelper($shortcodeHelper);
        $this->setGutenbergBlockHelper($blockHelper);
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
            } catch (\Exception $e) {
                $this->getLogger()->info(
                    vsprintf(
                        'Gutenberg not detected, skipping search for references in Gutenberg blocks for request %s',
                        [
                            var_export($this->getRequestSource(), true),
                        ]
                    )
                );
            }

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
                        $detectedReferences['images'] = array_unique($detectedReferences['images']);
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
