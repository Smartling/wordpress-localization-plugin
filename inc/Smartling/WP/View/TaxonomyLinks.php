<?php

use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\WP\Controller\TaxonomyLinksController;

/**
 * @var TaxonomyLinksController $this
 */

$siteList = [];
$currentBlogId = $this->siteHelper->getCurrentBlogId();
foreach ($this->siteHelper->listBlogs($this->siteHelper->getCurrentSiteId()) as $blogId) {
    if ($currentBlogId !== $blogId) {
        $siteList[$blogId] = $this->siteHelper->getBlogLabelById($this->localizationPluginProxy, $blogId);
    }
}
?>
<p>This section allows linking of taxonomy terms between different blogs to avoid target blog terms duplication upon translation.</p>
<p>Linked items will not be sent for translation.</p>
<h1>This action cannot be undone without editing database!</h1>
<h1 id="loading">Loading...</h1>
<form id="linkTaxonomyForm" style="display: none">
    <input type="hidden" name="sourceBlogId" value="<?= $currentBlogId?>">
    <label>Taxonomy
    <?= HtmlTagGeneratorHelper::tag(
        'select',
        HtmlTagGeneratorHelper::renderSelectOptions(null, get_taxonomies()),
        ['id' => 'taxonomy', 'name' => 'taxonomy']
    )?>
    </label>
    <label>Target blog id
    <?= HtmlTagGeneratorHelper::tag(
        'select',
        HtmlTagGeneratorHelper::renderSelectOptions(null, $siteList),
        ['id' => 'targetBlogId', 'name' => 'targetBlogId']
    )?>
    </label>
    <label>Source term
    <?= HtmlTagGeneratorHelper::tag(
        'select',
        [],
        ['id' => 'sourceId', 'name' => 'sourceId']
    )?>
    </label>
    <label>Target term
    <?= HtmlTagGeneratorHelper::tag(
        'select',
        [],
        ['id' => 'targetId', 'name' => 'targetId']
    )?></label>
    <input type="button" id="link" onclick="return false" value="Link">
</form>
