<?php

namespace Smartling\WP\Controller;

use Smartling\Bootstrap;
use Smartling\ContentTypes\ContentTypeInterface;
use Smartling\ContentTypes\ContentTypeManager;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\WordpressContentTypeHelper;
use WP_List_Table;

/**
 * Class SmartlingListTable
 * @package Smartling\WP\Controller
 */
class SmartlingListTable extends WP_List_Table
{

    private $source;

    /**
     * @return mixed
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * @param mixed $source
     */
    public function setSource($source)
    {
        $this->source = $source;
    }

    /**
     * @param SiteHelper $siteHelper
     *
     * @return array
     */
    protected function getActiveContentTypes(SiteHelper $siteHelper, $page = 'bulkSubmit')
    {
        $supportedTypes = WordpressContentTypeHelper::getLabelMap();

        $contentTypeManager = Bootstrap::getContainer()->get('content-type-descriptor-manager');
        /**
         * @var ContentTypeManager $contentTypeManager
         */

        $output = [];

        $registeredInWordpressTypes = array_merge($siteHelper->getPostTypes(), $siteHelper->getTermTypes());

        foreach ($supportedTypes as $contentTypeName => $contentTypeLabel) {
            /**
             * @var ContentTypeInterface $descriptor
             */
            $descriptor = $contentTypeManager->getDescriptorByType($contentTypeName);
try {
    if (!($descriptor instanceof ContentTypeInterface)) {
        continue;
    }
} catch (\Exception $e){}
            if (true === $descriptor->getVisibility()[$page]) {
                if ($descriptor->isVirtual() || in_array($contentTypeName, $registeredInWordpressTypes, true)) {
                $output[$contentTypeName]=$contentTypeLabel;
                }
            }
        }

        //Bootstrap::DebugPrint($supportedTypes, true);
        //$specialTypes = [WordpressContentTypeHelper::CONTENT_TYPE_WIDGET,];

        //$postTypes = $siteHelper->getPostTypes();
        //$termTypes = $siteHelper->getTermTypes();

        //$activeTypes = $specialTypes;

        //$activeTypes = array_merge($activeTypes, $postTypes, $termTypes);

        //$types = [];

        //foreach ($activeTypes as $activeType) {
        //    if (array_key_exists($activeType, $supportedTypes)) {
        //        $types[$activeType] = $supportedTypes[$activeType];
        //    }
        // }

        return $output;
    }

    /**
     * @param string $keyName
     * @param mixed  $defaultValue
     *
     * @return mixed
     */
    public function getFromSource($keyName, $defaultValue)
    {
        return array_key_exists($keyName, $this->getSource()) ? $this->getSource()[$keyName] : $defaultValue;
    }
}