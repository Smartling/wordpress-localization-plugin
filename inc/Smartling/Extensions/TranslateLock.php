<?php

namespace Smartling\Extensions;

use Psr\Log\LoggerInterface;
use Smartling\Bootstrap;
use Smartling\Exception\SmartlingDbException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\StringHelper;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

/**
 * Class TranslateLock
 * @package Smartling\Extensions
 */
class TranslateLock implements ExtensionInterface
{

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var SubmissionManager
     */
    private $submissionManager;

    /**
     * @var ContentHelper
     */
    private $contentHelper;

    /**
     * @return SubmissionManager
     */
    public function getSubmissionManager()
    {
        return $this->submissionManager;
    }

    /**
     * @param SubmissionManager $submissionManager
     */
    public function setSubmissionManager($submissionManager)
    {
        $this->submissionManager = $submissionManager;
    }

    /**
     * @return ContentHelper
     */
    public function getContentHelper()
    {
        return $this->contentHelper;
    }

    /**
     * @param ContentHelper $contentHelper
     */
    public function setContentHelper($contentHelper)
    {
        $this->contentHelper = $contentHelper;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        if (!($this->logger instanceof LoggerInterface)) {
            $this->setLogger(MonologWrapper::getLogger(__CLASS__));
        }

        return $this->logger;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function __construct(SubmissionManager $submissionManager, ContentHelper $contentHelper)
    {
        $this->setSubmissionManager($submissionManager);
        $this->setContentHelper($contentHelper);
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return __CLASS__;
    }

    /**
     * @param $id
     *
     * @return SubmissionEntity|null
     */
    private function getSubmissionById($id)
    {
        $_result = $this->getSubmissionManager()->getEntityById($id);

        return 1 === count($_result) ? ArrayHelper::first($_result) : null;
    }

    private function getTranslationFields($submissionId)
    {
        $submission = $this->getSubmissionById($submissionId);

        $_fields = [];
        if (null !== $submission) {
            foreach ($this->getContentHelper()->readTargetContent($submission)->toArray() as $k => $v) {
                $_fields['entity/' . $k] = $v;
            }
            foreach ($this->getContentHelper()->readTargetMetadata($submission) as $k => $v) {
                $_fields['meta/' . $k] = $v;
            }

        }

        return $_fields;
    }


    private function getLockedFields($submissionId)
    {
        $submission = $this->getSubmissionById($submissionId);

        $_fields = [];
        if (null !== $submission && 0 < strlen($submission->getLockedFields())) {
            $_fields = unserialize($submission->getLockedFields());
        }

        return $_fields;
    }


    /**
     * @inheritdoc
     */
    public function register()
    {
        add_action('post_submitbox_misc_actions', [$this, 'extendSubmitBox']);
        add_action('save_post', [$this, 'postSaveHandler']);
    }


    /**
     * @return int
     */
    private function getCurrentBlogId()
    {
        /**
         * @var SiteHelper $siteHelper
         */
        $siteHelper = Bootstrap::getContainer()->get('site.helper');

        return $siteHelper->getCurrentBlogId();
    }

    /**
     * @param $postId
     *
     * @return mixed|SubmissionEntity
     * @throws SmartlingDbException
     */
    private function getSubmission($postId)
    {
        $submissionManager = $this->getSubmissionManager();
        $submissions = $submissionManager->find(
            [
                'target_blog_id' => $this->getCurrentBlogId(),
                'target_id'      => $postId,
            ]
        );

        if (0 < count($submissions)) {
            $submission = ArrayHelper::first($submissions);

            /**
             * @var SubmissionEntity $submission
             */

            return $submission;
        } else {
            throw new SmartlingDbException ('No submission found');
        }
    }

    /**
     * Adds checkbox to Submit Widget of translation that locks translation for re-downloading
     */
    public function extendSubmitBox()
    {
        global $post;
        try {
            $submission = $this->getSubmission($post->ID);
            $locked = 1 === $submission->getIsLocked() ? 'yes' : '';

            ?>
            <style>
                #misc-publishing-actions label[for=locked_page]:before {
                    content: '\f173';
                    display: inline-block;
                    font: 400 20px/1 dashicons;
                    speak: none;
                    left: -1px;
                    padding: 0 5px 0 0;
                    position: relative;
                    top: 0;
                    text-decoration: none !important;
                    vertical-align: top;
                    -webkit-font-smoothing: antialiased;
                    -moz-osx-font-smoothing: grayscale;
                }

                #misc-publishing-actions a[class=thickbox]:before {
                    content: '\f163';
                    display: inline-block;
                    font: 400 20px/1 dashicons;
                    speak: none;
                    left: -1px;
                    padding: 0 5px 0 0;
                    position: relative;
                    top: 0;
                    text-decoration: none !important;
                    vertical-align: top;
                    -webkit-font-smoothing: antialiased;
                    -moz-osx-font-smoothing: grayscale;
                }

                table.fieldlist-table {
                    width: 100%;
                }

                table.fieldlist-table tr td:last-child {
                    max-width: 40px;
                }

                table.fieldlist-table tr {
                    width: 100%;
                }

                table.fieldlist-table {
                    border-collapse: collapse;
                }

                table.fieldlist-table, table.fieldlist-table tr td, table.fieldlist-table tr th {
                    border: 1px lightgrey solid;
                }

                table.fieldlist-table tr:nth-child(even) {
                    background-color: lightgrey;
                }

                /* The Modal (background) */
                .modal {
                    display: none; /* Hidden by default */
                    position: fixed; /* Stay in place */
                    z-index: 10000; /* Sit on top */
                    left: 0;
                    top: 0;
                    width: 100%; /* Full width */
                    height: 100%; /* Full height */
                    overflow: auto; /* Enable scroll if needed */
                    background-color: rgb(0, 0, 0); /* Fallback color */
                    background-color: rgba(0, 0, 0, 0.4); /* Black w/ opacity */
                }

                /* Modal Content/Box */
                .modal-content {
                    background-color: #fefefe;
                    margin: 10% auto; /* 15% from the top and centered */
                    padding: 20px;
                    border: 1px solid #888;
                    width: 80%; /* Could be more or less, depending on screen size */
                    max-width: 730px;
                    height: 60%;
                    overflow: auto;
                }

                /* The Close Button */
                .close {
                    color: #aaa;
                    float: right;
                    font-size: 28px;
                    font-weight: bold;
                }

                .close:hover,
                .close:focus {
                    color: black;
                    text-decoration: none;
                    cursor: pointer;
                }

            </style>
            <div id="locked_page_block" class="misc-pub-section">
                <label for="locked_page"><?= __('Translation Locked'); ?></label>
                <input id="locked_page" type="checkbox" value="yes"
                       name="lock_page" <?php checked('yes', $locked); ?> />
                <input type="hidden" name="locked_page_form_flag" value="1"/>
                <br>

                <div id="field_lock_block_wizard" class="modal">
                    <div class="modal-content">
                        <span class="close">&times;</span>
                        <div>
                            <?php
                            $fields = $this->getTranslationFields($submission->id);
                            $lockedFields = $this->getLockedFields($submission->id);
                            ?>

                            <table class="fieldlist-table">
                                <tr>
                                    <th>Field Name</th>
                                    <th>Field Value</th>
                                    <th>Locked</th>
                                </tr>
                                <?php foreach ($fields as $field => $value) {
                                    ?>
                                    <tr>
                                        <td><?= StringHelper::safeHtmlStringShrink($field, 30); ?></td>
                                        <td><?= StringHelper::safeHtmlStringShrink($value, 50); ?></td>
                                        <td><?php
                                            $options = [
                                                'type'       => 'checkbox',
                                                'data-field' => $field,
                                                'name'       => vsprintf('lockField[%s]', [$field]),
                                            ];

                                            if (is_array($lockedFields) && in_array($field, $lockedFields)) {
                                                $options['checked'] = 'checked';
                                            }
                                            ?>
                                            <?= HtmlTagGeneratorHelper::tag('input', '', $options); ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div id="locked_fields_block" class="misc-pub-section">
                <button id="fieldLockModal">Field level lock settings.</button>
            </div>
            <script>
                (function () {
                    var Modal = function (triggerId, modalId) {
                        var modal = document.getElementById(modalId);
                        var btn = document.getElementById(triggerId);
                        var span = document.getElementsByClassName("close")[0];
                        btn.onclick = function (e) {
                            e.preventDefault();
                            modal.style.display = "block";
                        };
                        span.onclick = function () {
                            modal.style.display = "none";
                        };
                        window.onclick = function (event) {
                            if (event.target == modal) {
                                modal.style.display = "none";
                            }
                        };
                    };
                    Modal('fieldLockModal', 'field_lock_block_wizard');
                })();
            </script>
            <?php
        } catch (SmartlingDbException $e) {
            return;
        }
    }

    public function postSaveHandler($postId)
    {
        if (wp_is_post_revision($postId)) {
            return;
        }

        remove_action('save_post', [$this, 'postSaveHandler']);

        $flagName = 'locked_page_form_flag';

        if (array_key_exists($flagName, $_POST) && '1' === $_POST[$flagName]) {
            $curValue = array_key_exists('lock_page', $_POST) && 'yes' === $_POST['lock_page'] ? 1 : 0;

            try {
                $submission = $this->getSubmission($postId);
                $submission->setIsLocked($curValue);
                $submission->setLockedFields(serialize(array_keys($_POST['lockField'])));
                $this->getSubmissionManager()->storeEntity($submission);
            } catch (\Exception $e) {
                MonologWrapper::getLogger(get_class($this))
                    ->warning(vsprintf('Error marking submission as locked for post=%s', [$postId]));
            }
        }
    }
}