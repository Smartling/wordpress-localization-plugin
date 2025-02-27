<?php

namespace Smartling\WP\Controller;

use Smartling\Base\SmartlingCore;
use Smartling\Bootstrap;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\Submissions\SubmissionEntity;
use Smartling\WP\WPAbstract;
use Smartling\WP\WPHookInterface;

/**
 * Class CheckStatusController
 *
 * @package Smartling\WP\Controller
 */
class CheckStatusController extends WPAbstract implements WPHookInterface
{
    const string SUBMISSION_CHECKED_KEY = "smartling-page-checked-items";
    const string CACHE_SLIDE_EXPIRATION = "PT1H";
    const int CACHE_EXPIRATION       = 7200;

    public function wp_enqueue()
    {
        wp_enqueue_script($this->getPluginInfo()->getName() . "submission", $this->getPluginInfo()
                ->getUrl() . 'js/smartling-submissions-check.js', ['jquery'], $this->getPluginInfo()
            ->getVersion(), false);
    }

    public function register(): void
    {
        if (!DiagnosticsHelper::isBlocked() && current_user_can(SmartlingUserCapabilities::SMARTLING_CAPABILITY_WIDGET_CAP)) {
            add_action('wp_ajax_ajax_submissions_update_status', [$this, 'ajaxHandler']);
            add_action('admin_enqueue_scripts', [$this, 'wp_enqueue']);
        }
    }

    /**
     * @return array|bool
     */
    public function ajaxHandler()
    {
        if ($_REQUEST["action"] === "ajax_submissions_update_status") {

            $items = $this->checkItems($_REQUEST["ids"]);

            if (count($items) > 0) {
                /**
                 * @var SmartlingCore $ep
                 */
                $ep = Bootstrap::getContainer()->get('entrypoint');
                $results = $ep->bulkCheckByIds($items);

                $response = [];
                foreach ($results as $result) {
                    /** @var SubmissionEntity $result */
                    $response[] = ["id"         => $result->getId(), "status" => $result->getStatus(),
                                   "color"      => $result->getStatusColor(),
                                   "percentage" => $result->getCompletionPercentage(),];
                }
                wp_send_json($response);
            }
        }

        return false;
    }

    /**
     * @param array $items
     *
     * @return array
     */
    public function checkItems(array $items)
    {
        $result = [];
        $cache = $this->getCache();

        $cachedItems = $cache->get(self::SUBMISSION_CHECKED_KEY);

        $now = new \DateTime("now");
        $slide = new \DateTime("now");
        $slide = $slide->add(new \DateInterval(self::CACHE_SLIDE_EXPIRATION));
        foreach ($items as $item) {
            $isCached = false;
            if ($cachedItems) {
                foreach ($cachedItems as &$cachedItem) {
                    if ($cachedItem["item"] === $item) {
                        $isCached = true;
                        if ($cachedItem["expiration"] <= $now) {
                            $result[] = $item;
                            $cachedItem["expiration"] = $slide;
                        }
                        break;
                    }
                }
            }

            if (!$isCached) {
                /** @noinspection OffsetOperationsInspection */
                $cachedItems[] = ["item" => $item, "expiration" => $slide,];
                $result[] = $item;
            }
        }

        $cache->set(self::SUBMISSION_CHECKED_KEY, $cachedItems, self::CACHE_EXPIRATION);

        return $result;
    }
}
