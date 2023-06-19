<?php

namespace Smartling\WP\Controller;

use Smartling\Bootstrap;
use Smartling\Helpers\AdminNoticesHelper;
use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\Jobs\JobAbstract;
use Smartling\Queue\Queue;
use Smartling\Queue\QueueInterface;
use Smartling\Services\GlobalSettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\WP\Table\QueueManagerTableWidget;
use Smartling\WP\WPAbstract;
use Smartling\WP\WPHookInterface;

class ConfigurationProfilesController extends WPAbstract implements WPHookInterface
{

    public const ACTION_QUEUE_PURGE = 'queue_purge';
    public const ACTION_QUEUE_FORCE = 'queue_force';

    public const MENU_SLUG = 'smartling_configuration_profile_list';

    /**
     * @var Queue
     */
    private $queue;

    /**
     * @return Queue
     */
    public function getQueue()
    {
        return $this->queue;
    }

    /**
     * @param Queue $queue
     * @noinspection PhpUnused used in services.yml
     */
    public function setQueue($queue)
    {
        $this->queue = $queue;
    }

    public function wp_enqueue(): void
    {
        wp_enqueue_script(
            $this->getPluginInfo()->getName() . 'settings',
            $this->getPluginInfo()->getUrl() . 'js/smartling-connector-admin.js', ['jquery'],
            $this->getPluginInfo()->getVersion(),
            false
        );
        wp_enqueue_script(
            $this->getPluginInfo()->getName() . 'settings-admin-footer',
            $this->getPluginInfo()->getUrl() . 'js/smartling-connector-gutenberg-lock-attributes.js',
            [],
            $this->getPluginInfo()->getVersion(),
            false
        );
        wp_localize_script($this->getPluginInfo()->getName() . 'settings-admin-footer', 'smartling', [
            'addLockIdAttributeOnSave' => GlobalSettingsManager::isGenerateLockIdsEnabled(),
        ]);
        wp_register_style(
            $this->getPluginInfo()->getName(),
            $this->getPluginInfo()->getUrl() . 'css/smartling-connector-admin.css', [],
            $this->getPluginInfo()->getVersion(), 'all'
        );
        wp_enqueue_style($this->getPluginInfo()->getName());
    }

    /**
     * Registers wp hook handlers. Invoked by wordpress.
     */
    public function register(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'wp_enqueue']);
        add_action('admin_menu', [$this, 'menu']);
        add_action('network_admin_menu', [$this, 'menu']);
        add_action('admin_post_smartling_configuration_profile_list', [$this, 'listProfiles']);
        add_action('admin_post_smartling_download_log_file', [$this, 'downloadLog']);
        add_action('admin_post_smartling_zerolength_log_file', [$this, 'cleanLog']);
        add_action('admin_post_cnq', [$this, 'processCnqAction']);
    }

    public function processCnqAction(): void
    {
        wp_send_json($this->processCnq());
    }

    public function processCnq(): array
    {
        $response = [
            'status'   => [
                'code'    => 0,
                'message' => '',
            ],
            'errors'   => [],
            'messages' => [],
            'data'     => [],
        ];

        $action = $this->getFromRequest('_c_action', null);

        if (null === $action) {
            $response['status'] = [
                'code'    => 400,
                'message' => 'Bad Request',
            ];
            $response['errors']['_c_action'] = ['Cannot be empty.'];
            $response['messages'][] = 'Invalid action.';
            return $response;
        }

        $argument = $this->getFromRequest('argument', null);

        if (null === $argument) {
            $response['status'] = [
                'code'    => 400,
                'message' => 'Bad Request',
            ];
            $response['errors']['argument'] = ['Cannot be empty.'];
            $response['messages'][] = 'Invalid argument.';
            return $response;
        }

        switch ($action) {
            case self::ACTION_QUEUE_PURGE:
                try {
                    if ($argument === QueueInterface::VIRTUAL_UPLOAD_QUEUE) {
                        $submissions = $this->getManager()->find([
                            SubmissionEntity::FIELD_STATUS => SubmissionEntity::SUBMISSION_STATUS_NEW,
                        ]);
                        if (count($submissions) > 0) {
                            $ids = [];
                            foreach ($submissions as $submission) {
                                $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_CANCELLED);
                                $ids[] = $submission->getId();
                            }
                            /** @noinspection JsonEncodingApiUsageInspection */
                            $this->getLogger()->info('User cancelled submissionCount=' . count($submissions) . ' submissions, submissionIds="' . json_encode($ids) . '"');
                            $this->getManager()->storeSubmissions($submissions);
                        }
                    } else {
                        $this->getQueue()->purge($argument);
                    }
                    $response['status'] = [
                        'code' => 200,
                        'message' => 'Ok',
                    ];
                } catch (\Exception $e) {
                    $response['status'] = [
                        'code' => 500,
                        'message' => 'Internal Server Error',
                    ];
                    $response['messages'][] = $e->getMessage();
                }
                break;
            case self::ACTION_QUEUE_FORCE:
                try {
                    do_action($argument, JobAbstract::SOURCE_USER);
                    $response['status'] = [
                        'code' => 200,
                        'message' => 'Ok',
                    ];
                    $message = "$argument started";
                    AdminNoticesHelper::addSuccess($message);
                } catch (\Exception $e) {
                    $response['status'] = [
                        'code' => 500,
                        'message' => 'Internal Server Error',
                    ];
                    $response['messages'][] = $e->getMessage();
                    AdminNoticesHelper::addError($e->getMessage());
                }
                break;
            default:
                $response['status'] = [
                    'code' => 400,
                    'message' => 'Bad Request',
                ];
                $response['errors']['_c_action'] = ['Invalid action.'];
                $response['messages'][] = 'Invalid action.';
                break;
        }

        return $response;
    }

    public function menu()
    {
        add_submenu_page(
            'smartling-submissions-page',
            'Configuration profiles',
            'Settings',
            SmartlingUserCapabilities::SMARTLING_CAPABILITY_PROFILE_CAP,
            self::MENU_SLUG,
            [
                $this,
                'listProfiles',
            ]
        );
    }

    public function listProfiles()
    {

        $cnqTable = new QueueManagerTableWidget($this->getManager());
        $cnqTable->setQueue($this->getQueue());

        $profilesTable = new ConfigurationProfilesWidget($this->getPluginInfo()->getSettingsManager());
        $this->view(['profilesTable' => $profilesTable, 'cnqTable' => $cnqTable]);
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
            $this->getLogger()->emergency($row);
        }

    }



    public function cleanLog()
    {
        $fullFilename = Bootstrap::getLogFileName();

        if (file_exists($fullFilename) && is_writable($fullFilename)) {
            unlink($fullFilename);
        }

        wp_redirect(get_site_url() . '/wp-admin/admin.php?page=' . self::MENU_SLUG);
    }

    public function downloadLog()
    {
        $this->signLogFile();
        $fullFilename = Bootstrap::getLogFileName();

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

    /**
     * @param string $keyName
     * @param mixed  $defaultValue
     *
     * @return mixed
     */
    public function getFromRequest($keyName, $defaultValue)
    {
        return array_key_exists($keyName, $_REQUEST) ? $_REQUEST[$keyName] : $defaultValue;
    }

}