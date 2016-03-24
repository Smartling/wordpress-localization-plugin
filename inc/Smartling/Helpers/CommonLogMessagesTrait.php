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
        'Added to Upload queue entity = \'%s\', blog = \'%s\', id = \'%s\', targetBlog = \'%s\', locale = \'%s\'.';

    private static $MSG_DOWNLOAD_TRIGGERED =
        'File download for submission id = \'%s\' was added to Download Queue.';

    private static $MSG_CRON_CHECK =
        'Cron Job triggers submission status check for submission id = \'%s\' with status = \'%s\' for entity = \'%s\', blog = \'%s\', id = \'%s\', targetBlog = \'%s\', locale = \'%s\'.';

    private static $MSG_CRON_CHECK_RESULT =
        'Checked status for entity = \'%s\', blog = \'%s\', id = \'%s\', locale = \'%s\', approvedString = \'%s\', completedString = \'%s\'.';
}
