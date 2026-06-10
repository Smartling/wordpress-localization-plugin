<?php

namespace Smartling\WP\Controller;

use Smartling\ApiWrapperInterface;
use Smartling\Base\ExportedAPI;
use Smartling\Helpers\Cache;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\PluginInfo;
use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\Models\NotificationParameters;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\WP\WPHookInterface;

class LiveNotificationController implements WPHookInterface
{
    use LoggerSafeTrait;

    private const DELETE_NOTIFICATION_ACTION_NAME = 'smartling_delete_live_notification';

    private const FIREBASE_SPACE_ID = 'wordpress-connector';
    private const FIREBASE_OBJECT_ID = 'notifications';

    private const CONFIG_CACHE_KEY = 'progress_tracker_config_cache_key';
    private const CONFIG_CACHE_TIME_SEC = 3600;

    private const UI_NOTIFICATION_IDENTIFIER_CLASS = 'smartling-notification-wrapper';
    private const UI_NOTIFICATION_IDENTIFIER_CLASS_GENERAL = 'smartling-notification-wrapper-general';

    public const SEVERITY_WARNING = 'notification-warning';
    public const SEVERITY_SUCCESS = 'notification-success';
    public const SEVERITY_ERROR = 'notification-error';

    private ApiWrapperInterface $apiWrapper;
    private SettingsManager $settingsManager;
    private Cache $cache;
    private PluginInfo $pluginInfo;

    public function injectFirebaseLibs(): void
    {
        $jsPath = $this->pluginInfo->getUrl() . 'js/firebase/firebase-';
        foreach (['app', 'auth', 'database'] as $lib) {
            $scriptFile = $jsPath . $lib . '.js';
            wp_enqueue_script($scriptFile, $scriptFile);
        }
    }

    public function __construct(ApiWrapperInterface $apiWrapper, SettingsManager $settingsManager, Cache $cache, PluginInfo $pluginInfo)
    {
        $this->apiWrapper = $apiWrapper;
        $this->settingsManager = $settingsManager;
        $this->cache = $cache;
        $this->pluginInfo = $pluginInfo;
    }

    public function getAvailableConfigs(): array
    {
        $configs = $this->cache->get(static::CONFIG_CACHE_KEY);

        if (false === $configs) {
            $configs = [];

            $profiles = $this->settingsManager->getActiveProfiles();
            foreach ($profiles as $profile) {
                try {
                    if (1 === $profile->getEnableNotifications()) {
                        $token = $this->apiWrapper->getProgressToken($profile);
                        $configs[$profile->getProjectId()] = $token;
                    }
                } catch (\Exception $e) {
                    // Empty settings, can't fetch project details.
                }
            }

            $this->cache->set(static::CONFIG_CACHE_KEY, $configs, static::CONFIG_CACHE_TIME_SEC);
        }

        return $configs;
    }

    public function placeJsConfig(): void
    {
        $configs = \json_encode(array_values($this->getAvailableConfigs()), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $deleteEndpoint = vsprintf(
            '%s?action=%s',
            [
                admin_url('admin-ajax.php'),
                self::DELETE_NOTIFICATION_ACTION_NAME,
            ]
        );

        $firebaseIds = \json_encode([
            'space_id' => self::FIREBASE_SPACE_ID,
            'object_id' => self::FIREBASE_OBJECT_ID,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $wrapperClassName = static::UI_NOTIFICATION_IDENTIFIER_CLASS;
        $wrapperClassNameGeneral = static::UI_NOTIFICATION_IDENTIFIER_CLASS_GENERAL;
        $deleteNonce = wp_create_nonce(self::DELETE_NOTIFICATION_ACTION_NAME);

        echo <<<EOF
<script>
    var firebaseConfig = $configs;
    var deleteNotificationEndpoint = "$deleteEndpoint";
    var deleteNotificationNonce = "$deleteNonce";
    var firebaseIds = $firebaseIds;
    var notificationClassName = "$wrapperClassName";
    var notificationClassNameGeneral = "$wrapperClassNameGeneral";
</script>
EOF;
    }


    public function deleteNotificationAjaxHandler(): void
    {
        if (check_ajax_referer(self::DELETE_NOTIFICATION_ACTION_NAME, '_wpnonce', false) === false) {
            $this->getLogger()->warning(sprintf('Invalid nonce for action "%s" from userId=%d', self::DELETE_NOTIFICATION_ACTION_NAME, get_current_user_id()));
            wp_send_json(['code' => 'error', 'message' => 'Invalid nonce'], 403);
            return;
        }
        if (!current_user_can(SmartlingUserCapabilities::SMARTLING_CAPABILITY_WIDGET_CAP)) {
            $this->getLogger()->warning(sprintf('User %d lacks capability "%s"', get_current_user_id(), SmartlingUserCapabilities::SMARTLING_CAPABILITY_WIDGET_CAP));
            wp_send_json(['code' => 'error', 'message' => 'Insufficient permissions'], 403);
            return;
        }

        $data = $_POST;

        $projectId = $data['project_id'];
        $spaceId = $data['space_id'];
        $objectId = $data['object_id'];
        $recordId = $data['record_id'];

        try {
            $profile = $this->settingsManager->getActiveProfileByProjectId($projectId);
            $this->apiWrapper->deleteNotificationRecord($profile, $spaceId, $objectId, $recordId);
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

    public function placeRecordId($submissionEntity): void
    {
        if ($submissionEntity instanceof SubmissionEntity) {
            $recordId = static::getContentId($submissionEntity);
            echo <<<EOF
<script>
    var recordId="$recordId";
</script>
EOF;
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

            $pluginInfo = $this->pluginInfo;

            /**
             * injecting progress-tracker.js
             */
            add_action('admin_enqueue_scripts', static function () use ($pluginInfo) {
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

    public function adminMenuHandler(\WP_Admin_Bar $menuBar): void
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

    public static function getContentId(SubmissionEntity $submissionEntity): string
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

    public static function pushNotification(string $projectId, string $contentId, string $severity, string $message): void
    {
        do_action(ExportedAPI::ACTION_SMARTLING_PUSH_LIVE_NOTIFICATION, new NotificationParameters($contentId, $message, $projectId, $severity));
    }

    public function pushNotificationHandler(NotificationParameters $params): void
    {
        try {
            $this->apiWrapper->setNotificationRecord(
                $this->settingsManager->getActiveProfileByProjectId($params->getProjectId()),
                static::FIREBASE_SPACE_ID,
                static::FIREBASE_OBJECT_ID,
                $params->getContentId(),
                [
                    'message' => $params->getMessage(),
                    'severity' => $params->getSeverity(),
                ]
            );
        } catch (\Exception $e) {
        }
    }
}
