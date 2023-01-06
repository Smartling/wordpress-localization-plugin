<?php

use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\WP\Controller\TaxonomyLinksController;

/**
 * @var TaxonomyLinksController $this
 */
$blogs = $this->getViewData()['blogs'];
?>
<script>
    let submissions = <?= json_encode($this->getViewData()['submissions'])?>;
    const terms = <?= json_encode($this->getViewData()['terms'])?>;
</script>
<p>This section allows linking of taxonomy terms between different blogs to avoid target blog terms duplication upon translation.</p>
<p>Linked items will not be sent for translation.</p>
<h1></h1> <!--needed for admin notices-->
<form id="linkTaxonomyForm">
    <input type="hidden" id="sourceBlogId" name="sourceBlogId" value="<?= get_current_blog_id()?>">
    <table class="form-table" style="width: 50%">
        <tr>
            <th><label for="taxonomy">Taxonomy</label></th>
            <td><?= HtmlTagGeneratorHelper::tag(
                    'select',
                    HtmlTagGeneratorHelper::renderSelectOptions(null, get_taxonomies()),
                    ['id' => 'taxonomy', 'name' => 'taxonomy']
                )?>
            </td>
        </tr>
        <tr>
            <th><label for="sourceId">Term</label></th>
            <td><?= HtmlTagGeneratorHelper::tag(
                    'select',
                    '',
                    ['id' => 'sourceId', 'name' => 'sourceId']
                )?>
            </td>
        </tr>
        <?php foreach ($blogs as $blogId => $blogTitle) {?>
        <tr>
            <th><label for="targetId_<?= $blogId?>"><?= $blogTitle?></label></th>
            <td><?= HtmlTagGeneratorHelper::tag(
                    'select',
                    '',
                    ['id' => "targetId_$blogId", 'name' => "targetId[$blogId]"]
                )?>
            </td>
        </tr>
        <?php }?>
    </table>
    <input type="button" class="button button-primary" id="link" onclick="return false" value="Save">
</form>
