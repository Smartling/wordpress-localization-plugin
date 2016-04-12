<?php

namespace Smartling\Extensions;

use Smartling\Bootstrap;
use Smartling\Exception\SmartlingDbException;
use Smartling\Helpers\SiteHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

/**
 * Class TranslateLock
 *
 * @package Smartling\Extensions
 */
class TranslateLock implements ExtensionInterface
{

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return __CLASS__;
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
     * @return SubmissionManager
     */
    private function getSubmissionManager()
    {
        return Bootstrap::getContainer()
                        ->get('manager.submission');
    }

    /**
     * @return int
     */
    private function getCurrentBlogId()
    {
        /**
         * @var SiteHelper $siteHelper
         */
        $siteHelper = Bootstrap::getContainer()
                               ->get('site.helper');

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
        $submissions = $submissionManager->find([
            'target_blog_id' => $this->getCurrentBlogId(),
            'target_id'      => $postId,
        ]);

        if (0 < count($submissions)) {
            $submission = reset($submissions);

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
            </style>
            <div id="locked_page" class="misc-pub-section misc-pub-post-status">
                <label for="locked_page"><?= __('Translation Locked'); ?></label>
                <input id="locked_page" type="checkbox" value="yes" name="lock_page" <?php checked('yes',
                    $locked); ?> />
                <input type="hidden" name="locked_page_form_flag" value="1"/>
            </div>
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


        $flagName='locked_page_form_flag';


        if(array_key_exists($flagName, $_POST) && '1'===$_POST[$flagName])
        {
            $curValue = array_key_exists('lock_page', $_POST) && 'yes' === $_POST['lock_page'] ? 1 : 0;

            try {
                $submission = $this->getSubmission($postId);
                $submission->setIsLocked($curValue);
                $this->getSubmissionManager()
                    ->storeEntity($submission);
            } catch (\Exception $e) {

            }
        }
    }
}