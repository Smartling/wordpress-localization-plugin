<?php

namespace Smartling\WP\Controller;

use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\WP\WPAbstract;
use Smartling\WP\WPHookInterface;

class ContentEditJobController extends WPAbstract implements WPHookInterface
{
    protected $servedContentType = 'undefined';

    /**
     * @return string
     */
    public function getServedContentType()
    {
        return $this->servedContentType;
    }

    /**
     * @param string $servedContentType
     */
    public function setServedContentType($servedContentType)
    {
        $this->servedContentType = $servedContentType;
    }

    /**
     * Registers wp hook handlers. Invoked by wordpress.
     * @return void
     */
    public function register()
    {
        if (!DiagnosticsHelper::isBlocked()) {
            add_action('admin_enqueue_scripts', [$this, 'wp_enqueue'], 99);
            $action = 'edit_form_before_permalink';
            add_action($action, function () {
                global $post, $wp_meta_boxes;
                do_meta_boxes(get_current_screen(), 'top', $post);
                unset($wp_meta_boxes[get_post_type($post)]['top']);
            });
            add_action('add_meta_boxes', [$this, 'box']);
        }
    }

    public function wp_enqueue()
    {
        $resPath = $this->getPluginInfo()->getUrl();
        $jsPath = $resPath . 'js/';
        $cssPath = $resPath . 'css/';
        $ver = $this->getPluginInfo()->getVersion();

        $jsFiles = [
            'select2.min.js',
            'jquery.datetimepicker.js',
        ];
        foreach ($jsFiles as $jFile) {
            $jFile = $jsPath . $jFile;
            wp_enqueue_script($jFile, $jFile, ['jquery',/* 'jqueru-ui'*/], $ver, false);
        }

        $cssFiles = [
            'select2.min.css',
            'jquery.datetimepicker.css',
        ];

        foreach ($cssFiles as $cssFile) {
            $cssFile = $cssPath . $cssFile;
            wp_register_style($cssFile, $cssFile, [], $ver, 'all');
            wp_enqueue_style($cssFile);
        }
    }


    public function box($contentType)
    {
        if ($this->getServedContentType() === $contentType) {
            // add ajax handler.
            add_meta_box('smartling.job.' . $contentType, 'Jobs', function ($meta_id) {


                $currentBlogId = $this->getEntityHelper()->getSiteHelper()->getCurrentBlogId();
                $applicableProfiles = $this->getEntityHelper()->getSettingsManager()
                    ->findEntityByMainLocale($currentBlogId);
                if (0 === count($applicableProfiles)) {
                    echo HtmlTagGeneratorHelper::tag('p', __('No suitable profile found for this site.'));
                } else {
                    $profile = ArrayHelper::first($applicableProfiles);

                    $this->view(
                        [
                            'profile' => $profile,
                            'b'       => 'b',
                            'c'       => 'c',
                        ]
                    );
                }


            },
                         'post',
                         'top',
                         'high'
            );
        }
    }
}