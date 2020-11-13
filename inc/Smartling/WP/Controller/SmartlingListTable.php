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
     * @param string $page
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

            if (!($descriptor instanceof ContentTypeInterface)) {
                continue;
            }

            if (true === $descriptor->getVisibility()[$page]) {
                if ($descriptor->isVirtual() || in_array($contentTypeName, $registeredInWordpressTypes, true)) {
                    $output[$contentTypeName] = $contentTypeLabel;
                }
            }
        }

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