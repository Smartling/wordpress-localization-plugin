<?php

namespace Smartling\WP\Controller;

use Smartling\Bootstrap;
use Smartling\ContentTypes\ContentTypeManager;
use Smartling\Helpers\DateTimeHelper;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\WordpressContentTypeHelper;
use WP_List_Table;

class SmartlingListTable extends WP_List_Table
{
    private array $source;

    public function setSource(array $source): void
    {
        $this->source = $source;
    }

    public function sqlToReadableDate(string $date): string
    {
        return '0000-00-00 00:00:00' === $date ? __('Never') : DateTimeHelper::toWordpressLocalDateTime(DateTimeHelper::stringToDateTime($date));
    }

    protected function getActiveContentTypes(SiteHelper $siteHelper, string $page = 'bulkSubmit'): array
    {
        $supportedTypes = WordpressContentTypeHelper::getLabelMap();

        $contentTypeManager = Bootstrap::getContainer()->get('content-type-descriptor-manager');
        if (!$contentTypeManager instanceof ContentTypeManager) {
            throw new \RuntimeException('Expected ' . ContentTypeManager::class, ' got ' . get_class($contentTypeManager) . ' from container (content-type-descriptor-manager)');
        }

        $output = [];

        $registeredInWordpressTypes = array_merge($siteHelper->getPostTypes(), $siteHelper->getTermTypes());

        foreach ($supportedTypes as $contentTypeName => $contentTypeLabel) {
            $descriptor = $contentTypeManager->getDescriptorByType($contentTypeName);

            if ($descriptor->isVisible($page) &&
                ($descriptor->isVirtual() || in_array($contentTypeName, $registeredInWordpressTypes, true))) {
                $output[$contentTypeName] = $contentTypeLabel;
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
    public function getFromSource(string $keyName, $defaultValue)
    {
        return array_key_exists($keyName, $this->source) ? ($this->source)[$keyName] : $defaultValue;
    }
}
