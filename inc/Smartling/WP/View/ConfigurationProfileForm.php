<?php

use Smartling\Exception\BlogNotFoundException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Helpers\Parsers\IntegerParser;
use Smartling\Helpers\StringHelper;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;
use Smartling\WP\Controller\ConfigurationProfileFormController;
use Smartling\WP\WPAbstract;

/**
 * @var ConfigurationProfileFormController $this
 * @var SettingsManager $settingsManager
 */
$settingsManager = $this->getViewData();

$pluginInfo = $this->getPluginInfo();
$domain = $pluginInfo->getDomain();

$profileId = 0;

if (array_key_exists('profile', $_GET)) {
    IntegerParser::tryParseString($_GET['profile'], $profileId);
}

const ERROR_TARGET_LOCALES_MESSAGE = 'Profile not saved: locale mappings must be unique for each blog';

if (array_key_exists('error', $_GET) && $_GET['error'] === ConfigurationProfileFormController::ERROR_TARGET_LOCALES) {
    $message = __(ERROR_TARGET_LOCALES_MESSAGE, $domain);
    echo "<div class=\"notice notice-error is-dismissible\"><p>$message</p></div>";
}

$defaultFilter = Smartling\Bootstrap::getContainer()->getParameter('field.processor.default');

?>
<script>
    (function ($) {
        $(function () {
            const queryProxy = {
                baseEndpoint: '<?= admin_url('admin-ajax.php') ?>?action=smartling_test_connection',
                getProjectLocales: function (params, success) {
                    $.post(this.baseEndpoint, params, function (response) {
                        success(response);
                    });
                },
            };
            const mkblockTag = function (tag, content, attributes) {
                if (undefined === attributes) {
                    attributes = {};
                }
                if (undefined === content) {
                    content = '';
                }
                let attributesPart = '';
                for (const v in attributes) {
                    if (attributes.hasOwnProperty(v)) {
                        attributesPart += ' ' + v + '="' + attributes[v] + '"';
                    }
                }
                return '<' + tag + attributesPart + '>' + content + '</' + tag + '>';
            };
            const mkTag = function (tag, attributes) {
                if (undefined === attributes) {
                    attributes = {};
                }
                let attributesPart = '';
                for (const v in attributes) {
                    if (attributes.hasOwnProperty(v)) {
                        attributesPart += ' ' + v + '="' + attributes[v] + '"';
                    }
                }
                return '<' + tag + attributesPart + '/>';
            };
            const getSelect = function (selectName, options, value) {
                const opts = [];
                for (let e in options) {
                    if (options.hasOwnProperty(e)) {
                        const attributes = {
                            value: e,
                        }
                        if (value === e) {
                            attributes.selected = 'selected';
                        }
                        opts.push(mkblockTag('option', options[e], attributes));
                    }
                }
                return mkblockTag('select', opts.join(''), {name: selectName});
            };

            $('#testConnection').on('click', function (e) {
                e.stopPropagation();
                e.preventDefault();

                queryProxy.getProjectLocales({
                    profileId: '<?= $profileId ?>',
                    projectId: $('#project-id').val(),
                    userIdent: $('#userIdentifier').val(),
                    tokenSecret: $('#secretKey').val()
                }, function (r) {
                    let i;
                    if (200 === r.status && undefined !== r.locales) {

                        const inputs = $('#target-locale-block input[type=text]');
                        for (i = 0; i < inputs.length; i++) {
                            const el = inputs[i];
                            const _name = $(el).attr('name');
                            const _value = $(el).val();
                            const select = getSelect(_name, r.locales, _value);
                            const place = $(el).parent();
                            place.html(select);
                        }

                        alert('Test successful.');
                    } else {
                        const selects = $('#target-locale-block select');
                        if (0 < selects.length) {
                            for (i = 0; i < selects.length; i++) {
                                const el = selects[i];
                                const _name = $(el).attr('name');
                                const _value = $(el).val();
                                const input = mkTag('input', {type: 'text', name: _name, value: _value});
                                const place = $(el).parent();
                                place.html(input);
                            }
                        }

                        alert('Test failed.');
                    }
                });
                return false;
            });
        });
    })(jQuery);
</script>
<?php

if (0 === $profileId) {
    $profile = $settingsManager->createProfile(
        [
            'auto_authorize'   => true,
            'upload_on_update' => true,
            'retrieval_type'   => 'published',
        ]
    );

    $profile->setFilterSkip(implode(PHP_EOL, $defaultFilter['ignore']));
    $profile->setFilterFlagSeo(implode(PHP_EOL, $defaultFilter['key']['seo']));
    $profile->setFilterCopyByFieldName(implode(PHP_EOL, $defaultFilter['copy']['name']));
    $profile->setFilterCopyByFieldValueRegex(implode(PHP_EOL, $defaultFilter['copy']['regexp']));
} else {
    $profiles = $pluginInfo->getSettingsManager()->getEntityById($profileId);

    $profile = ArrayHelper::first($profiles);

    ?>
    <script>
        (function ($) {
            $(function () {
                const getButton = function (selector, action, text) {
                    return '<button class="filter-' + selector + '" data-action="' + action + '">' + text + '</button>';
                };

                /* update UI */
                const ignoreActionBlock = '<br/>' + getButton('ignore', 'reset', '<?= __('Reset value') ?>') + '&nbsp;' + getButton('ignore', 'undo', '<?= __('Undo changes') ?>');
                const copyByNameActionBlock = '<br/>' + getButton('copy-name', 'reset', '<?= __('Reset value') ?>') + '&nbsp;' + getButton('copy-name', 'undo', '<?= __('Undo changes') ?>');
                const copyByValueActionBlock = '<br/>' + getButton('copy-value', 'reset', '<?= __('Reset value') ?>') + '&nbsp;' + getButton('copy-value', 'undo', '<?= __('Undo changes') ?>');
                const seoActionBlock = '<br/>' + getButton('seo', 'reset', '<?= __('Reset value') ?>') + '&nbsp;' + getButton('seo', 'undo', '<?= __('Undo changes') ?>');

                const filterSkip = $('#filter-skip');
                const filterCopyByName = $('#filter-copy-by-name');
                const filterCopyByValue = $('#filter-copy-by-value');
                const filterSetFlagSeo = $('#filter-set-flag-seo');

                filterSkip.after(ignoreActionBlock);
                filterCopyByName.after(copyByNameActionBlock);
                filterCopyByValue.after(copyByValueActionBlock);
                filterSetFlagSeo.after(seoActionBlock);

                /* add handlers */
                $('.filter-ignore').on('click', function (e) {
                    e.stopPropagation();
                    e.preventDefault();

                    switch ($(this).attr('data-action')) {
                        case 'undo':
                            filterSkip.val(<?= json_encode($profile->getFilterSkip(), JSON_THROW_ON_ERROR) ?>);
                            break;
                        case 'reset':
                            filterSkip.val(<?= json_encode(implode(PHP_EOL, $defaultFilter['ignore']), JSON_THROW_ON_ERROR) ?>);
                            break;
                        default:
                            throw {
                                "message": 'Unexpected value'
                            }
                    }
                });

                $('.filter-copy-name').on('click', function (e) {
                    e.stopPropagation();
                    e.preventDefault();

                    switch ($(this).attr('data-action')) {
                        case 'undo':
                            filterCopyByName.val(<?= json_encode($profile->getFilterCopyByFieldName(), JSON_THROW_ON_ERROR) ?>);
                            break;
                        case 'reset':
                            filterCopyByName.val(<?= json_encode(implode(PHP_EOL, $defaultFilter['copy']['name']), JSON_THROW_ON_ERROR) ?>);
                            break;
                        default:
                            throw {
                                "message": 'Unexpected value'
                            }
                    }
                });

                $('.filter-copy-value').on('click', function (e) {
                    e.stopPropagation();
                    e.preventDefault();

                    switch ($(this).attr('data-action')) {
                        case 'undo':
                            filterCopyByValue.val(<?= json_encode($profile->getFilterCopyByFieldValueRegex(), JSON_THROW_ON_ERROR) ?>);
                            break;
                        case 'reset':
                            filterCopyByValue.val(<?= json_encode(implode(PHP_EOL, $defaultFilter['copy']['regexp']), JSON_THROW_ON_ERROR) ?>);
                            break;
                        default:
                            throw {
                                "message": 'Unexpected value'
                            }
                    }
                });

                $('.filter-seo').on('click', function (e) {
                    e.stopPropagation();
                    e.preventDefault();

                    switch ($(this).attr('data-action')) {
                        case 'undo':
                            filterSetFlagSeo.val(<?= json_encode($profile->getFilterFlagSeo(), JSON_THROW_ON_ERROR) ?>);
                            break;
                        case 'reset':
                            filterSetFlagSeo.val(<?= json_encode(implode(PHP_EOL, $defaultFilter['key']['seo']), JSON_THROW_ON_ERROR) ?>);
                            break;
                        default:
                            throw {
                                "message": 'Unexpected value'
                            }
                    }
                });
            });
        })(jQuery);
    </script>
    <?php
}
?>

<div class="wrap">
    <h2><?= __(get_admin_page_title(), $domain) ?></h2>
    <form id="smartling-configuration-profile-form" action="<?= get_admin_url(null, 'admin-post.php') ?>" method="POST">
        <?= HtmlTagGeneratorHelper::tag('input', '', [
            'type'  => 'hidden',
            'name'  => 'action',
            'value' => 'smartling_configuration_profile_save',
        ]) ?>

        <?= HtmlTagGeneratorHelper::tag('input', '', [
            'type'  => 'hidden',
            'name'  => 'smartling_settings[id]',
            'value' => $profile->getId(),
        ]) ?>

        <?php wp_nonce_field('smartling_connector_settings', 'smartling_connector_nonce'); ?>
        <?php wp_referer_field(); ?>

        <h3><?= __('Account Info', $domain) ?></h3>
        <table class="form-table">
            <tbody>

            <tr>
                <th scope="row">
                    <label for="profileName">
                        <?= __(ConfigurationProfileEntity::getFieldLabel('profile_name'), $domain) ?>
                    </label>
                </th>
                <td>
                    <?=
                    HtmlTagGeneratorHelper::tag('input', '', [
                        'type'        => 'text',
                        'id'          => 'profileName',
                        'name'        => 'smartling_settings[profileName]',
                        'placeholder' => __('Set profile name', $domain),
                        'data-msg'    => __('Please set name for profile', $domain),
                        'required'    => 'required',
                        'value'       => htmlentities($profile->getProfileName()),
                    ])
                    ?>
                    <br>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="is_active">
                        <?= ConfigurationProfileEntity::getFieldLabel('is_active') ?>
                    </label>
                </th>
                <td>
                    <?=
                    HtmlTagGeneratorHelper::tag(
                        'select',
                        HtmlTagGeneratorHelper::renderSelectOptions(
                            $profile->getIsActive(),
                            [
                                '1' => __('Active', $domain),
                                '0' => __('Inactive', $domain),
                            ]
                        ),
                        [
                            'id'   => 'is_active',
                            'name' => 'smartling_settings[active]',
                        ]
                    )
                    ?>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="project-id">
                        <?= __('Project ID', $domain) ?>
                    </label>
                </th>
                <td>
                    <?=
                    HtmlTagGeneratorHelper::tag(
                        'input',
                        '',
                        [
                            'type'                => 'text',
                            'name'                => 'smartling_settings[projectId]',
                            'id'                  => 'project-id',
                            'required'            => 'required',
                            'placeholder'         => __('Set project ID', $domain),
                            'data-msg'            => __('Please set project ID', $domain),
                            'data-rule-minlength' => 9,
                            'data-rule-maxlength' => 9,
                            'data-msg-minlength'  => __('Project ID is 9 chars length.', $domain),
                            'data-msg-maxlength'  => __('Project ID is 9 chars length.', $domain),
                            'value' => $profile->getProjectId(),
                        ]
                    )
                    ?>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="userIdentifier">
                        <?= __('User Identifier', $domain) ?>
                    </label>
                </th>
                <td>
                    <?=
                    HtmlTagGeneratorHelper::tag(
                        'input',
                        '',
                        [
                            'type'        => 'text',
                            'name'        => 'smartling_settings[userIdentifier]',
                            'id'          => 'userIdentifier',
                            'required'    => 'required',
                            'placeholder' => __('Set the User Identifier', $domain),
                            'data-msg'    => __('User Identifier should be set', $domain),
                            'value'       => $profile->getUserIdentifier(),
                        ]
                    )
                    ?>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="secretKey">
                        <?= __('Token Secret', $domain) ?>
                    </label>
                </th>
                <td>
                    <?php
                    $key = $profile->getSecretKey();

                    $tokenOptions = [
                        'type' => 'text',
                        'name' => 'smartling_settings[secretKey]',
                        'id'   => 'secretKey',

                    ];

                    if (StringHelper::isNullOrEmpty($key)) {
                        $tokenOptions['required'] = 'required';
                        $tokenOptions['placeholder'] = __('Set the Token Secret', $domain);
                        $tokenOptions['data-msg'] = __('Token Secret should be set', $domain);
                    } else {
                        $tokenOptions['placeholder'] = __('Enter new Token to update', $domain);
                    }

                    ?>
                    <?= HtmlTagGeneratorHelper::tag('input', '', $tokenOptions) ?>
                    <input type="button" id="testConnection" value="Test Connection"/>
                    <br>
                    <?php if ($key): ?>
                        <small><?= __('Current Key', $domain) ?>: <?= substr(htmlspecialchars($key), 0, -10) . '**********' ?></small>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?= __('Source Locale', $domain) ?></th>
                <td>
                    <?php
                    $locales = [];
                    foreach ($settingsManager->getSiteHelper()->listBlogs() as $blogId) {
                        try {
                            $locales[$blogId] =
                                $settingsManager->getSiteHelper()
                                    ->getBlogLabelById($settingsManager->getPluginProxy(), $blogId);
                        } catch (BlogNotFoundException $e) {
                            $this->getLogger()->warning($e->getMessage());
                        }
                    }
                    ?>
                    <?php if (0 === $profileId): ?>
                        <?php
                        $tagOptions = ['prompt' => __('Please select source locale', $domain)];
                        $options = HtmlTagGeneratorHelper::renderSelectOptions(
                            $profile->getSourceLocale()->getBlogId(),
                            $locales,
                            $tagOptions
                        );
                        ?>
                        <?= HtmlTagGeneratorHelper::tag(
                            'select',
                            $options,
                            ['name'     => 'smartling_settings[defaultLocale]',
                             'required' => 'required',
                             'id'       => 'default-locales-new',]
                        ) ?>
                    <?php else: ?>
                        <p>
                            <?= __('Site source language is: ', $domain) ?>
                            <strong><?= $profile->getSourceLocale()->getLabel() ?></strong>
                        </p>
                        <p>
                            <a href="#" id="change-default-locale"><?= __('Change source locale', $domain) ?></a>
                        </p>
                        <br/>
                        <?= HtmlTagGeneratorHelper::tag(
                            'select',
                            HtmlTagGeneratorHelper::renderSelectOptions(
                                $profile->getSourceLocale()->getBlogId(),
                                $locales
                            ),
                            ['name' => 'smartling_settings[defaultLocale]',
                             'id'   => 'default-locales',]
                        ) ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?= __('Target Locales', $domain) ?></th>
                <td>
                    <?= WPAbstract::checkUncheckBlock('configuration-profile-form') ?>
                    <table id="target-locale-block">
                        <?php
                        $targetLocales = $profile->getTargetLocales();
                        $supportedLocales = $this->api->getSupportedLocales($profile);
                        foreach ($locales as $blogId => $label) {
                            if ($blogId === $profile->getSourceLocale()
                                    ->getBlogId()
                            ) {
                                continue;
                            }

                            $smartlingLocale = '';
                            $enabled = false;

                            foreach ($targetLocales as $targetLocale) {
                                if ($targetLocale->getBlogId() === $blogId) {
                                    $smartlingLocale = $targetLocale->getSmartlingLocale();
                                    $enabled = $targetLocale->isEnabled();
                                    break;
                                }
                            }
                            ?>

                            <tr>
                                <?= $this->renderLocales($supportedLocales, $label, $blogId, $smartlingLocale, $enabled) ?>
                            </tr>
                            <?php
                        }
                        ?>
                    </table>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <div class="update-nag">
                        <p>
                            <?= __("<strong>Warning!</strong>
Updates to these settings will change how content is handled during the translation process.<br>
Contact Technical Support or your Customer Success Manager before modifying these settings.<br>", $domain) ?>
                        </p>
                        <p>
                            <a href="javascript:void(0)"
                               class="toggleExpert"><strong><?= __('Show Expert Settings', $domain) ?></strong></a>
                        </p>
                    </div>
                </td>
            </tr>
            <tr class="toggleExpert hidden">
                <th colspan="2">
                    <h3><?= __('Upload Options', $domain) ?></h3>
                </th>
            </tr>
            <tr class="toggleExpert hidden">
                <th scope="row"><?= __('Resubmit changed content', $domain) ?></th>
                <td>
                    <?=
                    HtmlTagGeneratorHelper::tag(
                        'select',
                        HtmlTagGeneratorHelper::renderSelectOptions(
                            $profile->getUploadOnUpdate(),
                            [0 => __('Manually', $domain),
                             1 => __('Automatically', $domain),]

                        ),
                        ['name' => 'smartling_settings[uploadOnUpdate]'])

                    ?>
                    <br/>
                    <small>
                        <?= __('Detect and resubmit to Smartling changes in original content', $domain) ?>.
                    </small>
                </td>
            </tr>
            <tr class="toggleExpert hidden">
                <th scope="row"><?= __('Force sync attachment files on upload', $domain) ?></th>
                <td>
                    <?=
                    HtmlTagGeneratorHelper::tag(
                        'select',
                        HtmlTagGeneratorHelper::renderSelectOptions(
                            $profile->getAlwaysSyncImagesOnUpload(),
                            [0 => __('Disabled', $domain),
                             1 => __('Enabled', $domain),]

                        ),
                        ['name' => 'smartling_settings[always_sync_images_on_upload]'])

                    ?>
                    <br/>
                    <small>
                        <?= __('Replace target media file on every source media file submit', $domain) ?>.
                    </small>
                </td>
            </tr>
            <tr class="toggleExpert hidden">
                <th scope="row"><?= __('Live notifications', $domain) ?></th>
                <td>
                    <?=
                    HtmlTagGeneratorHelper::tag(
                        'select',
                        HtmlTagGeneratorHelper::renderSelectOptions(
                            $profile->getEnableNotifications(),
                            [0 => __('Disabled', $domain),
                             1 => __('Enabled', $domain),]

                        ),
                        ['name' => 'smartling_settings[enable_notifications]'])

                    ?>
                    <br/>
                    <small>
                        <?= __('Display notifications for background running tasks in Admin panel', $domain) ?>.
                    </small>
                </td>
            </tr>
            <tr class="toggleExpert hidden">
                <th scope="row"><?= __('Clone attachments') ?></th>
                <td>
                    <?=
                    HtmlTagGeneratorHelper::tag(
                        'select',
                        HtmlTagGeneratorHelper::renderSelectOptions(
                            $profile->getCloneAttachment(),
                            [
                                0 => __('Disabled', $domain),
                                1 => __('Enabled', $domain)
                            ]
                        ),
                        ['name' => 'smartling_settings[cloneAttachment]']
                    )
                    ?>
                    <br/>
                    <small>
                        <?= __('Always clone attachments', $domain) ?>.
                    </small>
                </td>
            </tr>
            <tr class="toggleExpert hidden">
                <th scope="row"><?= ConfigurationProfileEntity::getFieldLabel('auto_authorize') ?></th>
                <td>
                    <label class="radio-label">
                            <?php
                            $option = $profile->getAutoAuthorize();
                            $checked = $option === true ? 'checked="checked"' : '';
                            ?>
                            <input type="checkbox"
                                   name="smartling_settings[autoAuthorize]" <?= $checked ?> / >
                            <?= __('Auto authorize job', $domain) ?>
                    </label>
                </td>
            </tr>
            <tr class="toggleExpert hidden">
                <th colspan="2">
                    <h3><?= __('Download Options', $domain) ?></h3>
                </th>
            </tr>
            <tr class="toggleExpert hidden">
                <th scope="row"><?= __('Retrieval Type', $domain) ?></th>
                <td>
                    <?=
                    HtmlTagGeneratorHelper::tag(
                        'select',
                        HtmlTagGeneratorHelper::renderSelectOptions(
                            $profile->getRetrievalType(),
                            ConfigurationProfileEntity::getRetrievalTypes()
                        ),
                        ['name' => 'smartling_settings[retrievalType]'])

                    ?>
                    <br/>
                    <small><?= __('Param for download translate', $domain) ?>.
                    </small>
                </td>
            </tr>
            <tr class="toggleExpert hidden">
                <th scope="row"><?= __('Download translated files when',$domain) ?></th>
                <td>
                    <?=
                    HtmlTagGeneratorHelper::tag(
                        'select',
                        HtmlTagGeneratorHelper::renderSelectOptions(
                            $profile->getDownloadOnChange(),
                            [
                                ConfigurationProfileEntity::TRANSLATION_DOWNLOAD_MODE_TRANSLATION_COMPLETED => __('Translation Completed', $domain),
                                ConfigurationProfileEntity::TRANSLATION_DOWNLOAD_MODE_PROGRESS_CHANGES => __('Progress Changes', $domain),
                                ConfigurationProfileEntity::TRANSLATION_DOWNLOAD_MODE_MANUAL => __('Manual', $domain),
                            ]
                        ),
                        ['name' => 'smartling_settings[download_on_change]'])
                    ?>
                </td>
            </tr>
            <tr class="toggleExpert hidden">
                <th scope="row"><?= __('Change asset status on completed translation', $domain) ?></th>
                <td>
                    <?=
                    HtmlTagGeneratorHelper::tag(
                        'select',
                        HtmlTagGeneratorHelper::renderSelectOptions(
                            $profile->getTranslationPublishingMode(),
                            [
                                ConfigurationProfileEntity::TRANSLATION_PUBLISHING_MODE_NO_CHANGE => __('Don\'t change status', $domain),
                                ConfigurationProfileEntity::TRANSLATION_PUBLISHING_MODE_PUBLISH => __('Always publish', $domain),
                                ConfigurationProfileEntity::TRANSLATION_PUBLISHING_MODE_DRAFT => __('Always draft', $domain),
                            ]
                        ),
                        ['name' => 'smartling_settings[publish_completed]'])
                    ?>
                </td>
            </tr>
            <tr class="toggleExpert hidden">
                <th scope="row"><?= __('Auto synchronize properties on translated page with source', $domain) ?></th>
                <td>
                    <p>If enabled, Smartling will check for changes to the source’s properties, i.e. removing an image, changing placement of a text field, and automatically update translated pages.</p>
                    <?=
                    HtmlTagGeneratorHelper::tag(
                        'select',
                        HtmlTagGeneratorHelper::renderSelectOptions(
                            $profile->getCleanMetadataOnDownload(),
                            [
                                0 => __('Disabled', $domain),
                                1 => __('Enabled', $domain),
                            ]
                        ),
                        ['name' => 'smartling_settings[clean_metadata_on_download]'])
                    ?>
                </td>
            </tr>

            <tr class="toggleExpert hidden">
                <th scope="row"><?= __('Treat ' . ConfigurationProfileEntity::getFieldLabel('filter_skip') .
                        ' and ' . ConfigurationProfileEntity::getFieldLabel('filter_copy_by_field_name') .
                        ' as regex', $domain)?></th>
                <td>
                    <p>Exact match:</p>
                    <ul class="smartling-list">
                        <li>Each row is unique field.</li>
                        <li>Fields are case sensitive.</li>
                        <li>Field can be a content object property, meta key name, or a key of a serialized array.</li>
                    </ul>
                    <p>RegExp:</p>
                    <ul class="smartling-list">
                        <li>Each row a unique regular expression, delimiter is '/'</li>
                        <li>Regular expression has no modifiers, (no ignore case etc).</li>
                        <li>Fields will match even partially, for example regular expression 'a' will match
                            every field that has an a inside ("background", "hash", "parent", ...)</li>
                        <li>Field can be a content object property, meta key name, or a key of a serialized
                            array.</li>
                    </ul>
                    <?=
                    HtmlTagGeneratorHelper::tag(
                        'select',
                        HtmlTagGeneratorHelper::renderSelectOptions(
                            $profile->getFilterFieldNameRegExp() ? '1' : '0',
                            [
                                '0' => __('Exact match', $domain),
                                '1' => __('RegExp', $domain),
                            ]
                        ),
                        [
                                'id' => 'filter-field-name-regexp',
                                'name' => 'smartling_settings[' . ConfigurationProfileFormController::FILTER_FIELD_NAME_REGEXP . ']'
                        ]
                    )
                    ?>
                    <script>
                        document.getElementById('filter-field-name-regexp').addEventListener('change', function (e) {
                            for (const input of [
                                document.getElementById('filter-skip'),
                                document.getElementById('filter-copy-by-name'),
                            ]) {
                                input.value = input.value.split('\n')
                                    .map(e.target.value === '0' ? function removeRegexStartAndEnd (value) {
                                        if (value.length > 0) {
                                            if (value[0] === '^') {
                                                value = value.substring(1);
                                            }
                                            if (value[value.length - 1] === '$') {
                                                value = value.substring(0, value.length - 1);
                                            }
                                        }

                                        return value
                                    } : function addRegexStartAndEnd (value) {
                                        if (value.length > 0) {
                                            if (value[0] !== '^') {
                                                value = '^' + value;
                                            }
                                            if (value[value.length - 1] !== '$') {
                                                value = value + '$';
                                            }
                                        }

                                        return value;
                                    })
                                    .join('\n');
                            }
                        })
                    </script>
                </td>
            </tr>

            <tr class="toggleExpert hidden">
                <th scope="row"><?= ConfigurationProfileEntity::getFieldLabel('filter_skip') ?></th>
                <td>
                    <p>Fields listed here will be excluded
                        and not carried over during translation.</p>
                    <textarea id="filter-skip" wrap="off" cols="45" rows="5" class="nowrap"
                              name="smartling_settings[filter_skip]"><?= trim($profile->getFilterSkip()) ?></textarea>
                </td>
            </tr>
            <tr class="toggleExpert hidden">
                <th scope="row"><?= ConfigurationProfileEntity::getFieldLabel('filter_copy_by_field_name') ?></th>
                <td>
                    <p>Fields listed here will be excluded from translation
                        and copied over from the source content.</p>
                    <textarea id="filter-copy-by-name" wrap="off" cols="45" rows="5" class="nowrap"
                              name="smartling_settings[filter_copy_by_field_name]">
<?= trim($profile->getFilterCopyByFieldName())?></textarea>
                </td>
            </tr>
            <tr class="toggleExpert hidden">
                <th scope="row"><?= ConfigurationProfileEntity::getFieldLabel('filter_copy_by_field_value_regex') ?></th>
                <td>
                    <p>Regular expressions listed here will identify field names to exclude from translation and be
                        copied over from the source content.<br>
                        <small>Hints:<br>
                            <ul class="smartling-list">
                                <li>Each row is a unique regular expression</li>
                                <li>Regular expressions are applied without ignore case modifier.</li>
                            </ul>
                        </small>
                    </p>
                    <textarea id="filter-copy-by-value" wrap="off" cols="45" rows="5" class="nowrap"
                              name="smartling_settings[filter_copy_by_field_value_regex]"><?= trim($profile->getFilterCopyByFieldValueRegex()) ?></textarea>
                </td>
            </tr>
            <tr class="toggleExpert hidden">
                <th scope="row"><?= ConfigurationProfileEntity::getFieldLabel('filter_flag_seo') ?></th>
                <td>
                    <p>Fields listed here will be identified with a special ‘SEO’ key during translation.<br>
                        <small>Hints:<br>
                            <ul class="smartling-list">
                                <li>Each row is a unique field.</li>
                                <li>Fields are case sensitive.</li>
                            </ul>
                        </small>
                    </p>
                    <textarea id="filter-set-flag-seo" wrap="off" cols="45" rows="5" class="nowrap"
                              name="smartling_settings[filter_flag_seo]"><?= trim($profile->getFilterFlagSeo()) ?></textarea>
                </td>
            </tr>
            </tbody>
        </table>
        <div class="notice-error" id="errorDiv" style="display: none"></div>
        <?php submit_button(); ?>
    </form>
    <script>
        document.getElementById('smartling-configuration-profile-form').addEventListener('submit', function submitForm(event) {
            const errorDiv = document.getElementById('errorDiv');
            errorDiv.style.display = 'none';
            const usedSmartlingLocales = {};
            for (const element of document.querySelectorAll('.targetLocaleSelectCell select')) {
                const input = document.querySelector(
                    `input.mcheck[name="smartling_settings[targetLocales][${element.name.match(/\[(\d+)]/)[1]}][enabled]"]`
                );

                if (input && input.checked) {
                    if (usedSmartlingLocales.hasOwnProperty(element.value)) {
                        event.preventDefault();
                        errorDiv.innerText = `<?= ERROR_TARGET_LOCALES_MESSAGE?>. ${element.value} is being used more than once.`;
                        errorDiv.style.display = 'block';
                        break;
                    }
                    usedSmartlingLocales[element.value] = true;
                }
            }
        });
    </script>
</div>
