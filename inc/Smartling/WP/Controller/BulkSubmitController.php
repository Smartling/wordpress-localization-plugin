<?php
namespace Smartling\WP\Controller;

use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\WP\View\BulkSubmitTableWidget;
use Smartling\WP\WPAbstract;
use Smartling\WP\WPHookInterface;

/**
 * Class BulkSubmitController
 * @package Smartling\WP\Controller
 */
class BulkSubmitController extends WPAbstract implements WPHookInterface
{

    /**
     * @inheritdoc
     */
    public function register()
    {
        if (!DiagnosticsHelper::isBlocked()) {
            add_action('admin_menu', [$this, 'menu']);
            add_action('admin_enqueue_scripts', [$this, 'wp_enqueue']);
        }
    }

    public function wp_enqueue()
    {
        $resPath = $this->getPluginInfo()->getUrl();
        $jsPath = $resPath . 'js/';
        $ver = $this->getPluginInfo()->getVersion();
        wp_enqueue_script('jquery');
        $jsFiles = [
            $jsPath . 'bulk-submit.js',
        ];
        foreach ($jsFiles as $jFile) {
            wp_enqueue_script($jFile, $jFile, ['jquery'], $ver, false);
        }
    }

    public function menu()
    {
        add_submenu_page(
            'smartling-submissions-page',
            'Bulk Submit',
            'Bulk Submit',
            SmartlingUserCapabilities::SMARTLING_CAPABILITY_MENU_CAP,
            'smartling-bulk-submit',
            [
                $this,
                'renderPage',
            ]
        );
    }

    public function renderPage()
    {
        $currentBlogId = $this->getEntityHelper()->getSiteHelper()->getCurrentBlogId();
        $applicableProfiles = $this->getEntityHelper()->getSettingsManager()->findEntityByMainLocale($currentBlogId);
        if (0 === count($applicableProfiles)) {
            echo HtmlTagGeneratorHelper::tag('p', __('No suitable profile found for this site.'));
        } else {
            $profile = ArrayHelper::first($applicableProfiles);
            $table = new BulkSubmitTableWidget(
                $this->getManager(),
                $this->getPluginInfo(),
                $this->getEntityHelper(),
                $profile
            );
            $this->view($table);
        }
    }
}