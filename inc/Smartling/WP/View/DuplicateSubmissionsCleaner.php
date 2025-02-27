<?php

use Smartling\WP\Table\DuplicateSubmissions;

?>
<div class="wrap">
    <h2><?= get_admin_page_title() ?></h2>
    <h3>Please review submissions where the same source content ID points to multiple targets</h3>
    <ul>
        <li>If no edit link is available, you need to manually review the content and ids specified and remove the unneeded relations.</li>
        <li>If the target content ID is 0, it is safe to remove.</li>
    </ul>
    <?php
    $duplicates = $this->getViewData()['duplicates'];
    foreach ($duplicates as $set) {
        assert($set instanceof DuplicateSubmissions);
        $details = $set->getDuplicateSubmissionDetails();
        $editLink = $set->getEditLink($details->contentType, $details->sourceId);
        echo '<h4>Duplicate submissions for ';
        if ($editLink !== null) {
            echo "<a href='$editLink'>";
        }
        echo "{$details->contentType} id {$details->sourceId}";
        if ($editLink !== null) {
            echo '</a>';
        }
        echo " in target blog {$details->targetBlogId}</h4>";
        $set->prepare_items();
        $set->display();
    }
    ?>
</div>
