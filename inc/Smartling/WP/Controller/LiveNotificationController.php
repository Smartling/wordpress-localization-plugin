<?php

namespace Smartling\WP\Controller;

use Psr\Log\LoggerInterface;
use Smartling\ApiWrapperInterface;
use Smartling\Base\ExportedAPI;
use Smartling\Helpers\Cache;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\PluginInfo;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\WP\WPHookInterface;

/**
 * Class LiveNotificationController
 * @package Smartling\WP\Controller
 */
class LiveNotificationController implements WPHookInterface
{
    const FIREBASE_CDN_PATH = 'https://www.gstatic.com/firebasejs';

    const FIREBASE_LIB_VERSION = '5.2.0';

    const DELETE_NOTIFICATION_ACTION_NAME = 'smartling_delete_live_notification';

    private static $firebaseLibs = ['app', 'auth', 'database'];

    const FIREBASE_SPACE_ID = 'wordpress-connector';

    const FIREBASE_OBJECT_ID = 'notifications';

    const CONFIG_CACHE_KEY = 'progress_tracker_config_cache_key';

    const CONFIG_CACHE_TIME_SEC = 3600;

    const UI_NOTIFICATION_IDENTIFIER_CLASS = 'smartling-notification-wrapper';

    const UI_NOTIFICATION_IDENTIFIER_CLASS_GENERAL = 'smartling-notification-wrapper-general';

    const SEVERITY_WARNING = 'notification-warning';

    const SEVERITY_SUCCESS = 'notification-success';

    const SEVERITY_ERROR = 'notification-error';

    /**
     * @var ApiWrapperInterface
     */
    private $apiWrapper;

    /**
     * @var SettingsManager
     */
    private $settingsManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Cache
     */
    private $cache;

    /**
     * @var PluginInfo
     */
    private $pluginInfo;

    /**
     * @return ApiWrapperInterface
     */
    public function getApiWrapper()
    {
        return $this->apiWrapper;
    }

    /**
     * @param ApiWrapperInterface $apiWrapper
     *
     * @return LiveNotificationController
     */
    public function setApiWrapper($apiWrapper)
    {
        $this->apiWrapper = $apiWrapper;

        return $this;
    }

    /**
     * @return SettingsManager
     */
    public function getSettingsManager()
    {
        return $this->settingsManager;
    }

    /**
     * @param SettingsManager $settingsManager
     *
     * @return LiveNotificationController
     */
    public function setSettingsManager($settingsManager)
    {
        $this->settingsManager = $settingsManager;

        return $this;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param LoggerInterface $logger
     *
     * @return LiveNotificationController
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * @return Cache
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * @param Cache $cache
     *
     * @return LiveNotificationController
     */
    public function setCache($cache)
    {
        $this->cache = $cache;

        return $this;
    }

    /**
     * @return PluginInfo
     */
    public function getPluginInfo()
    {
        return $this->pluginInfo;
    }

    /**
     * @param PluginInfo $pluginInfo
     *
     * @return LiveNotificationController
     */
    public function setPluginInfo($pluginInfo)
    {
        $this->pluginInfo = $pluginInfo;

        return $this;
    }

    public function injectFirebaseLibs()
    {
        foreach (static::$firebaseLibs as $lib) {
            $scriptFile = vsprintf('%s/%s/firebase-%s.js', [self::FIREBASE_CDN_PATH, self::FIREBASE_LIB_VERSION, $lib]);
            wp_enqueue_script($scriptFile, $scriptFile);
        }
    }

    public function __construct(ApiWrapperInterface $apiWrapper, SettingsManager $settingsManager, Cache $cache, PluginInfo $pluginInfo)
    {
        $this
            ->setApiWrapper($apiWrapper)
            ->setSettingsManager($settingsManager)
            ->setCache($cache)
            ->setPluginInfo($pluginInfo)
            ->setLogger(MonologWrapper::getLogger(__CLASS__));
    }

    /**
     * @return array
     */
    public function getAvailableConfigs()
    {
        $configs = $this->getCache()->get(static::CONFIG_CACHE_KEY);

        if (false === $configs) {
            $configs = [];

            $profiles = $this->getSettingsManager()->getActiveProfiles();
            foreach ($profiles as $profile) {
                /**
                 * @var ConfigurationProfileEntity $profile
                 */
                try {
                    if (1 === $profile->getEnableNotifications()) {
                        $token = $this->getApiWrapper()->getProgressToken($profile);
                        $configs[$profile->getProjectId()] = $token;
                    }
                } catch (\Exception $e) {
                    // Empty settings, can't fetch project details.
                }
            }

            $this->getCache()->set(static::CONFIG_CACHE_KEY, $configs, static::CONFIG_CACHE_TIME_SEC);
        }

        return $configs;
    }

    public function placeJsConfig()
    {
        $configs = \json_encode(array_values($this->getAvailableConfigs()), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $deleteEndpoint = vsprintf(
            '%s?action=%s',
            [
                admin_url('admin-ajax.php'),
                self::DELETE_NOTIFICATION_ACTION_NAME,
            ]
        );

        $firebaseIds = \json_encode(
            [
                'space_id'  => self::FIREBASE_SPACE_ID,
                'object_id' => self::FIREBASE_OBJECT_ID,
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        );

        $wrapperClassName = static::UI_NOTIFICATION_IDENTIFIER_CLASS;
        $wrapperClassNameGeneral = static::UI_NOTIFICATION_IDENTIFIER_CLASS_GENERAL;

        $script = <<<EOF
<script>
    var firebaseConfig = {$configs};
    var deleteNotificationEndpoint = "{$deleteEndpoint}";
    var firebaseIds = {$firebaseIds};
    var notificationClassName = "{$wrapperClassName}";
    var notificationClassNameGeneral = "{$wrapperClassNameGeneral}";
</script>
EOF;

        echo $script;
    }


    public function deleteNotificationAjaxHandler()
    {
        $data = &$_POST;

        $projectId = $data['project_id'];
        $spaceId = $data['space_id'];
        $objectId = $data['object_id'];
        $recordId = $data['record_id'];

        try {
            $profile = $this->getSettingsManager()->getActiveProfileByProjectId($projectId);
            $this->getApiWrapper()->deleteNotificationRecord($profile, $spaceId, $objectId, $recordId);
            $result = [
                'code' => 'success',
            ];
        } catch (\Exception $e) {
            $result = [
                'code'    => 'error',
                'message' => $e->getMessage(),
            ];
        }
        wp_send_json($result);
    }

    public function placeRecordId($submissionEntity)
    {
        if ($submissionEntity instanceof SubmissionEntity) {
            $recordId = static::getContentId($submissionEntity);
            $script = <<<EOF
<script>
    var recordId="{$recordId}";
</script>
EOF;
            echo $script;
        }
    }

    /**
     * Registers wp hook handlers. Invoked by wordpress.
     */
    public function register(): void
    {
        if (!DiagnosticsHelper::isBlocked()) {
            /**
             * injecting config
             */
            add_action('admin_head', [$this, 'placeJsConfig']);

            /**
             * injecting firebase libs
             */
            add_action('admin_enqueue_scripts', [$this, 'injectFirebaseLibs']);

            $pluginInfo = $this->getPluginInfo();

            /**
             * injecting progress-tracker.js
             */
            add_action('admin_enqueue_scripts', function () use ($pluginInfo) {
                $scriptFileName = vsprintf(
                    '%sjs/%s',
                    [
                        $pluginInfo->getUrl(),
                        'progress-tracker.js',
                    ]
                );
                wp_enqueue_script($scriptFileName, $scriptFileName, [], $pluginInfo->getVersion(), false);
            });

            /**
             * Registering endpoint to delete notification
             */
            add_action(
                vsprintf('wp_ajax_%s', [self::DELETE_NOTIFICATION_ACTION_NAME]),
                [
                    $this,
                    'deleteNotificationAjaxHandler',
                ]
            );

            /**
             * Registering hook to add notification
             */
            add_action(ExportedAPI::ACTION_SMARTLING_PUSH_LIVE_NOTIFICATION, [$this, 'pushNotificationHandler']);

            add_action(ExportedAPI::ACTION_SMARTLING_PLACE_RECORD_ID, [$this, 'placeRecordId']);

            add_action('admin_bar_menu', [$this, 'adminMenuHandler'], 80);
        }
    }

    public function adminMenuHandler(\WP_Admin_Bar $menuBar)
    {
        $menuBar
            ->add_menu([
                           'id'    => 'smartling-live-menu',
                           'title' => '<span class="circle"></span> Smartling Live',
                           'href'  => '#',
                           'meta'  => [
                               'class' => 'smartling-live-menu',
                           ],
                       ]
            );
    }

    /**
     * @param SubmissionEntity $submissionEntity
     *
     * @return string
     */
    public static function getContentId(SubmissionEntity $submissionEntity)
    {
        return md5(
            serialize(
                [
                    $submissionEntity->getSourceBlogId(),
                    $submissionEntity->getSourceId(),
                    $submissionEntity->getContentType(),
                ]
            )
        );
    }

    public static function pushNotification($projectId, $contentId, $severity, $message)
    {
        do_action(ExportedAPI::ACTION_SMARTLING_PUSH_LIVE_NOTIFICATION,

                  [
                      'projectId'  => $projectId,
                      'content_id' => $contentId,
                      'message'    => [
                          'severity' => $severity,
                          'message'  => $message,
                      ],
                  ]

        );
    }

    public function pushNotificationHandler($params)
    {
        $projectId = $params['projectId'];
        $contentId = $params['content_id'];
        $message = $params['message'];
        try {
            $profile = $this->getSettingsManager()->getActiveProfileByProjectId($projectId);

            $this->getApiWrapper()->setNotificationRecord(
                $profile,
                static::FIREBASE_SPACE_ID,
                static::FIREBASE_OBJECT_ID,
                $contentId,
                $message
            );
        } catch (\Exception $e) {
        }
    }
}