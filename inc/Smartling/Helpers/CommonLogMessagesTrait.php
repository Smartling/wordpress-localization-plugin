<?php

namespace Smartling\Helpers;

/**
 * Class CommonLogMessagesTrait
 *
 * @package Smartling\Helpers
 */
trait CommonLogMessagesTrait
{

    private static $MSG_CLONING_CONTENT =
        'Cloning entity="%s", blog="%s", id="%s", targetBlog="%s", locale="%s".';

    private static $MSG_UPLOAD_ENQUEUE_ENTITY =
        'Added to Upload queue entity="%s", blog="%s", id="%s", targetBlog="%s", locale="%s".';

    private static $MSG_UPLOAD_ENQUEUE_ENTITY_JOB =
        'Added to Upload queue entity="%s", blog="%s", id="%s", targetBlog="%s", locale="%s", job="%s", batch="%s".';

    private static $MSG_DOWNLOAD_ENQUEUE_ENTITY =
        'Added to Download queue submission id="%s" with status="%s" for entity="%s", blog="%s", id="%s", targetBlog="%s", locale="%s".';

    private static $MSG_CRON_CHECK =
        'Cron Job triggers submission status check for submission id="%s" with status="%s" for entity="%s", blog="%s", id="%s", targetBlog="%s", locale="%s".';

    private static $MSG_CRON_CHECK_RESULT =
        'Checked status for entity="%s", blog="%s", id="%s", locale="%s", approvedString="%s", completedString="%s".';
}
