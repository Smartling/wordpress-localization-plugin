<?php

use Smartling\WP\WPAbstract;

/**
 * @var WPAbstract $this
 * @var WPAbstract self
 */

$this->setWidgetHeader(__('Download translation:'));
$this->renderViewScript('post-based-content-type.php');
