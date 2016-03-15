<?php

namespace Smartling\WP\Controller;

use Smartling\Bootstrap;
use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\Jobs\UploadJob;
use Smartling\WP\JobEngine;
use Smartling\WP\WPAbstract;
use Smartling\WP\WPHookInterface;

/**
 * Class ConfigurationProfilesController
 *
 * @package Smartling\WP\Controller
 */
class ConfigurationProfilesController extends WPAbstract implements WPHookInterface
{

    public function wp_enqueue()
    {
        wp_enqueue_script(
            $this->getPluginInfo()
                 ->getName() . 'settings',
            $this->getPluginInfo()
                 ->getUrl() . 'js/smartling-connector-admin.js',
            ['jquery'],
            $this->getPluginInfo()
                 ->getVersion(),
            false
        );
        wp_register_style(
            $this->getPluginInfo()
                 ->getName(),
            $this->getPluginInfo()
                 ->getUrl() . 'css/smartling-connector-admin.css',
            [],
            $this->getPluginInfo()
                 ->getVersion(),
            'all'
        );
        wp_enqueue_style($this->getPluginInfo()
                              ->getName());
    }

    /**
     * Registers wp hook handlers. Invoked by wordpress.
     *
     * @return void
     */
    public function register()
    {
        add_action('admin_enqueue_scripts', [$this, 'wp_enqueue']);

        add_action('admin_menu', [$this, 'menu']);
        add_action('network_admin_menu', [$this, 'menu']);

        add_action('admin_post_smartling_configuration_profile_edit', [$this, 'edit']);
        add_action('admin_post_smartling_configuration_profile_list', [$this, 'listProfiles']);


        add_action('admin_post_smartling_run_cron', [$this, 'runCron']);

        add_action('admin_post_smartling_download_log_file', [$this, 'downloadLog']);
    }

    public function menu()
    {
        add_submenu_page(
            'smartling-submissions-page',
            'Configuration profiles',
            'Settings',
            SmartlingUserCapabilities::SMARTLING_CAPABILITY_PROFILE_CAP,
            'smartling_configuration_profile_list',
            [
                $this,
                'listProfiles',
            ]
        );
    }

    /**
     * Starts cron job
     *
     * @throws \Exception
     */
    public function runCron()
    {
        ignore_user_abort(true);
        set_time_limit(0);

        /**
         * @var UploadJob $uploadJob
         */
        $uploadJob = Bootstrap::getContainer()->get('cron.worker.upload');
        do_action($uploadJob->getJobHookName());


        wp_die('Cron job triggered. Now you can safely close this window / browser tab.');
    }

    public function listProfiles()
    {
        $table = new ConfigurationProfilesWidget($this->getPluginInfo()
                                                      ->getSettingsManager());
        $this->view($table);
    }

    private function signLogFile()
    {
        global $wp_version;
        $sign = [
            '*********************************************************',
            '* Plugin version:    ' . Bootstrap::getCurrentVersion(),
            '* PHP version:       ' . phpversion(),
            '* Wordpress version: ' . $wp_version,
            '* Wordpress hostname: ' . Bootstrap::getHttpHostName(),
            '*********************************************************',
        ];

        foreach ($sign as $row) {
            $this->getLogger()
                 ->emergency($row);
        }

    }

    public function downloadLog()
    {
        $container = Bootstrap::getContainer();
        $this->signLogFile();

        $pluginDir = $container->getParameter('plugin.dir');
        $filename = $container->getParameter('logger.filehandler.standard.filename');

        $fullFilename = vsprintf('%s-%s',
            [str_replace('%plugin.dir%', $pluginDir, $filename), date('Y-m-d')]);

        if (file_exists($fullFilename) && is_readable($fullFilename)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($fullFilename) . '.txt"');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . filesize($fullFilename)); //Remove
            readfile($fullFilename);
        }
    }

}