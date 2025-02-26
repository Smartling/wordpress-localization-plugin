<?php

namespace Smartling\Helpers;

use Smartling\WP\WPHookInterface;

class AdminNoticesHelper implements WPHookInterface
{
    public const string OPTION_NAME = 'smartling_notices';

    public function register(): void
    {
        add_action('admin_notices', [$this, 'displayNotices']);
    }

    public function displayNotices(): void
    {
        foreach (self::getNotices() as $type => $messages) {
            foreach ($messages as $message) {
                printf('<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_html($type), esc_html($message));
            }
        }

        self::updateNotices();
    }

    private static function getNotices(): array
    {
        return get_option(self::OPTION_NAME, []);
    }

    private static function updateNotices(array $notices = []): void
    {
        update_option(self::OPTION_NAME, $notices);
    }

    private static function addNotice(string $message, string $type): void
    {
        $notices = self::getNotices();
        $notices[$type][] = $message;
        self::updateNotices($notices);
    }

    public static function addSuccess(string $message): void
    {
        self::addNotice($message, 'success');
    }

    public static function addError(string $message): void
    {
        self::addNotice($message, 'error');
    }

    public static function addWarning(string $message): void
    {
        self::addNotice($message, 'warning');
    }

    public static function addInfo(string $message): void
    {
        self::addNotice($message, 'info');
    }
}
