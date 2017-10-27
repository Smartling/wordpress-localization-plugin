<?php

namespace Smartling\WP\Controller;

use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\WP\WPAbstract;
use Smartling\WP\WPHookInterface;

class ContentEditJobController extends WPAbstract implements WPHookInterface
{
    /**
     * @var string
     */
    protected $servedContentType = 'undefined';

    /**
     * @var string
     */
    protected $baseType = 'post';

    /**
     * @return string
     */
    public function getBaseType()
    {
        return $this->baseType;
    }

    /**
     * @param string $baseType
     */
    public function setBaseType($baseType)
    {
        $this->baseType = $baseType;
    }

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

            switch ($this->getBaseType()) {
                case 'post':
                    $action = 'edit_form_before_permalink';
                    add_action($action, function () {
                        global $post, $wp_meta_boxes;
                        do_meta_boxes(get_current_screen(), 'top', $post);
                        unset($wp_meta_boxes[get_post_type($post)]['top']);
                    });
                    add_action('add_meta_boxes', [$this, 'box']);
                    break;
                case 'taxonomy':
                    add_action("{$this->getServedContentType()}_edit_form", [$this, 'box'], 99, 1);
                    break;
                default:
            }


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


    public function box($attr)
    {
        $contentType = ($attr instanceof \WP_Term) ? $attr->taxonomy : $attr;
        if ($this->getServedContentType() === $contentType) {
            if ($attr instanceof \WP_Term) {
                $currentBlogId = $this->getEntityHelper()->getSiteHelper()->getCurrentBlogId();
                $applicableProfiles = $this->getEntityHelper()->getSettingsManager()
                    ->findEntityByMainLocale($currentBlogId);
                if (0 === count($applicableProfiles)) {
                    echo HtmlTagGeneratorHelper::tag('p', __('No suitable profile found for this site.'));
                } else {
                    $profile = ArrayHelper::first($applicableProfiles);
                    $this->view(
                        [
                            'profile'     => $profile,
                            'contentType' => $contentType,
                        ]
                    );
                }
            } else {
                $id = 'smartling.job.' . $contentType;
                add_meta_box($id, 'Jobs', function ($meta_id) use ($contentType) {
                    $currentBlogId = $this->getEntityHelper()->getSiteHelper()->getCurrentBlogId();
                    $applicableProfiles = $this->getEntityHelper()->getSettingsManager()->findEntityByMainLocale($currentBlogId);
                    if (0 === count($applicableProfiles)) {
                        echo HtmlTagGeneratorHelper::tag('p', __('No suitable profile found for this site.'));
                    } else {
                        $profile = ArrayHelper::first($applicableProfiles);
                        $this->view(
                            [
                                'profile'     => $profile,
                                'contentType' => $contentType,
                            ]
                        );
                    }
                }, $contentType, 'top', 'high');
            }
        }
    }
}