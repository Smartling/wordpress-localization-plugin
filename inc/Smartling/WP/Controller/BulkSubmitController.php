<?php
namespace Smartling\WP\Controller;

use Smartling\Bootstrap;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\WP\Table\BulkSubmitTableWidget;
use Smartling\WP\WPAbstract;
use Smartling\WP\WPHookInterface;

class BulkSubmitController extends WPAbstract implements WPHookInterface
{
    public function register(): void
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
        $currentBlogId = $this->siteHelper->getCurrentBlogId();
        $applicableProfiles = $this->settingsManager->findEntityByMainLocale($currentBlogId);
        if (0 === count($applicableProfiles)) {
            echo HtmlTagGeneratorHelper::tag('p', __('No suitable profile found for this site.'));
        } else {
            $profile = ArrayHelper::first($applicableProfiles);
            $table = new BulkSubmitTableWidget(
                $this->localizationPluginProxy,
                $this->siteHelper,
                Bootstrap::getContainer()->get('entrypoint'),
                $this->getManager(),
                $this->getPluginInfo(),
                $profile
            );
            $this->view($table);
        }
    }
}