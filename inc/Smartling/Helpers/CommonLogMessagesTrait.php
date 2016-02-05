<?php

namespace Smartling\Helpers;

/**
 * Class CommonLogMessagesTrait
 *
 * @package Smartling\Helpers
 */
trait CommonLogMessagesTrait
{

    private static $MSG_ENQUEUE_ENTITY =
        'Added to queue entity = \'%s\', blog = \'%s\', id = \'%s\', targetBlog = \'%s\', locale = \'%s\'';

    private static $MSG_WARN_UNKNOWN_ACTION_TRIGGERED =
        '! An unknown action = \'%s\' for submission id = \'%s\' triggered.';

    private static $MSG_STATUS_CHECK_TRIGGERED =
        'Status check for submission id = \'%s\' triggered.';

    private static $MSG_UPLOAD_TRIGGERED =
        'File upload for submission id = \'%s\' triggered.';

    private static $MSG_DOWNLOAD_TRIGGERED =
        'File download for submission id = \'%s\' triggered.';

    private static $MSG_CRON_INITIAL_SUMMARY =
        'Found %s submissions.';

    private static $MSG_CRON_SEND =
        'Cron Job triggers content upload for submission id = \'%s\' with status = \'%s\' for entity = \'%s\', blog = \'%s\', id = \'%s\', targetBlog = \'%s\', locale = \'%s\'';

    private static $MSG_CRON_CHECK =
        'Cron Job triggers submission status check for submission id = \'%s\' with status = \'%s\' for entity = \'%s\', blog = \'%s\', id = \'%s\', targetBlog = \'%s\', locale = \'%s\'';

    private static $MSG_CRON_DOWNLOAD =
        'Cron Job triggers content download for submission id = \'%s\' with status = \'%s\' for entity = \'%s\', blog = \'%s\', id = \'%s\', targetBlog = \'%s\', locale = \'%s\'';

    private static $MSG_CRON_CHECK_RESULT =
        'Checked status for entity = \'%s\', blog = \'%s\', id = \'%s\', locale = \'%s\', approvedString = \'%s\', completedString = \'%s\'';
}