<?php

namespace Smartling\WP\Controller;

use Smartling\Helpers\DetectChangesHelper;

/**
 * Class DetectContentChangeTrait
 * @package Smartling\WP\Controller
 */
trait DetectContentChangeTrait
{
    /**
     * @var DetectChangesHelper
     */
    private $detectChangesHelper;

    /**
     * @return DetectChangesHelper
     */
    public function getDetectChangesHelper()
    {
        return $this->detectChangesHelper;
    }

    /**
     * @param DetectChangesHelper $detectChangesHelper
     */
    public function setDetectChangesHelper($detectChangesHelper)
    {
        $this->detectChangesHelper = $detectChangesHelper;
    }


    /**
     * @param int    $sourceBlogId
     * @param int    $sourceId
     * @param string $contentType
     */
    public function detectChange($sourceBlogId, $sourceId, $contentType)
    {
        $this->getLogger()->debug(
            vsprintf(
                'Checking if content has changed for %s blog=%s, id=%s started.',
                [
                    $contentType,
                    $sourceBlogId,
                    $sourceId,
                ]
            )
        );

        $this->getDetectChangesHelper()
            ->detectChanges($sourceBlogId, $sourceId, $contentType);

        $this->getLogger()->debug(
            vsprintf(
                'Checking if content has changed for %s blog=%s, id=%s finished.',
                [
                    $contentType,
                    $sourceBlogId,
                    $sourceId,
                ]
            )
        );
    }
}