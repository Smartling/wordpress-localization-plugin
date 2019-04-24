<?php

namespace Smartling\WP\Controller;

use DateTimeZone;
use Exception;
use Smartling\Bootstrap;
use Smartling\Exceptions\SmartlingApiException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\DateTimeHelper;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\Jobs\JobStatus;
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

    public function initJobApiProxy()
    {
        add_action('wp_ajax_' . 'smartling_job_api_proxy', function () {

            $data =& $_POST;

            $result = [
                'status' => 200,
            ];

            $wrapper = Bootstrap::getContainer()->get('wrapper.sdk.api.smartling');
            /**
             * @var ApiWrapper $wrapper
             */

            $siteHelper = Bootstrap::getContainer()->get('site.helper');
            /**
             * @var SiteHelper $siteHelper
             */

            $settingsManager = Bootstrap::getContainer()->get('manager.settings');
            /**
             * @var SettingsManager $settingsManager
             */

            $curSiteId = $siteHelper->getCurrentBlogId();
            $profile = $settingsManager->getSingleSettingsProfile($curSiteId);
            $params = &$data['params'];

            $validateRequires = function ($fieldName) use (&$result, $params) {
                if (array_key_exists($fieldName, $params) && '' !== ($value = trim($params[$fieldName]))) {
                    return $value;
                } else {
                    $msg = vsprintf('The field \'%s\' cannot be empty', [$fieldName]);
                    Bootstrap::getLogger()->warning($msg);
                    $result['status'] = 400;
                    $result['message'][$fieldName] = $msg;
                }
            };

            if (array_key_exists('innerAction', $data)) {
                switch ($data['innerAction']) {
                    case 'list-jobs' :
                        $jobs = $wrapper->listJobs($profile, null, [
                            JobStatus::AWAITING_AUTHORIZATION,
                            JobStatus::IN_PROGRESS,
                            JobStatus::COMPLETED,
                        ]);
                        $preparcedJobs = [];
                        if (is_array($jobs) && array_key_exists('items', $jobs) &&
                            array_key_exists('totalCount', $jobs) && 0 < (int)$jobs['totalCount']) {
                            foreach ($jobs['items'] as $job) {
                                if (!empty($job['dueDate'])) {
                                    $job['dueDate'] = \DateTime::createFromFormat('Y-m-d\TH:i:s\Z', $job['dueDate'])
                                        ->format(DateTimeHelper::DATE_TIME_FORMAT_JOB);
                                }

                                $preparcedJobs[] = $job;
                            }
                        }
                        $result['data'] = $preparcedJobs;
                        break;
                    case 'create-job':
                        $jobName = $validateRequires('jobName');
                        $timezone = $validateRequires('timezone');
                        $jobDescription = $params['description'];
                        $jobDueDate = $params['dueDate'];
                        $jobLocalesRaw = explode(',', $validateRequires('locales'));
                        $jobLocales = [];
                        foreach ($jobLocalesRaw as $blogId) {
                            $jobLocales[] = $settingsManager->getSmartlingLocaleIdBySettingsProfile($profile, (int)$blogId);
                        }
                        $debug['status'] = $result['status'];
                        if ($result['status'] === 200) {
                            try {
                                // Convert user's time to UTC.
                                $utcDateTime = '';

                                if (!empty($jobDueDate)) {
                                    $utcDateTime = \DateTime::createFromFormat(DateTimeHelper::DATE_TIME_FORMAT_JOB, $jobDueDate, new DateTimeZone($timezone));
                                    $utcDateTime->setTimeZone(new DateTimeZone('UTC'));
                                }

                                $res = $wrapper->createJob($profile, [
                                    'name'        => $jobName,
                                    'description' => $jobDescription,
                                    'dueDate'     => $utcDateTime,
                                    'locales'     => $jobLocales,
                                ]);
                                $res['dueDate'] = empty($res['dueDate'])
                                    ? $res['dueDate']
                                    : \DateTime::createFromFormat('Y-m-d\TH:i:s\Z', $res['dueDate'])
                                        ->format(DateTimeHelper::DATE_TIME_FORMAT_JOB);
                                $result['data'] = $res;
                            } catch (SmartlingApiException $e) {
                                $result['message']['global'] = vsprintf('Cannot create job \'%s\'', [$jobName]);
                                $result['status'] = 400;

                                foreach ($e->getErrors() as $message) {
                                    $result['message'][$message['details']['field']] = $message['message'];
                                }
                            } catch (Exception $e) {
                                $error_msg = $e->getMessage();
                                $result['status'] = 400;
                                $result['message']['global'] = $error_msg;
                                $result['message'] = [$error_msg];
                            }
                        } else {
                            $result['message']['global'] = 'Please fix issues to continue';
                        }
                        break;
                    default:
                        $result['status'] = 400;
                        $result['message']['global'] = 'Invalid inner action.';
                        break;
                }
            }
            wp_send_json($result);
        });
    }

    /**
     * Registers wp hook handlers. Invoked by wordpress.
     * @return void
     */
    public function register()
    {
        if (!DiagnosticsHelper::isBlocked() &&
            current_user_can(SmartlingUserCapabilities::SMARTLING_CAPABILITY_WIDGET_CAP)) {
            add_action('admin_enqueue_scripts', [$this, 'wp_enqueue'], 99);

            $this->initJobApiProxy();

            switch ($this->getBaseType()) {
                case 'post':
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
            'smartling.dtpicker.conflict.resolver.saver.js',
            'jquery.datetimepicker.js',
            'smartling.dtpicker.conflict.resolver.js',
            'moment.js',
            'moment-timezone-with-data.js',
        ];
        foreach ($jsFiles as $jFile) {
            $jFile = $jsPath . $jFile;
            wp_enqueue_script($jFile, $jFile, ['jquery',/* 'jqueru-ui'*/], $ver, false);
        }

        $cssFiles = [
            'select2.min.css',
            'jquery.datetimepicker.css',
            'jobs.css',
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
                add_meta_box($id, 'Smartling Upload Widget', function ($meta_id) use ($contentType) {
                    $currentBlogId = $this->getEntityHelper()->getSiteHelper()->getCurrentBlogId();
                    $applicableProfiles = $this
                        ->getEntityHelper()
                        ->getSettingsManager()
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
                }, $contentType, 'normal', 'high');
            }
        }
    }
}