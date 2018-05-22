<?php

namespace Smartling\WP\Controller;

use Smartling\Exception\SmartlingDbException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\WP\Table\TranslationLockTableWidget;
use Smartling\WP\WPAbstract;
use Smartling\WP\WPHookInterface;

class TranslationLockController extends WPAbstract implements WPHookInterface
{

    /**
     * @var ContentHelper
     */
    private $contentHelper;

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
     * Registers wp hook handlers. Invoked by wordpress.
     * @return void
     */
    public function register()
    {
        add_action('post_submitbox_misc_actions', [$this, 'extendSubmitBox']);

        add_action('admin_post_smartling_translation_lock_popup', [$this, 'popupIFrame']);
    }

    /**
     * @param $postId
     *
     * @return mixed|SubmissionEntity
     * @throws SmartlingDbException
     * @throws \Smartling\Exception\SmartlingDirectRunRuntimeException
     */
    private function getSubmission($postId)
    {
        $submissionManager = $this->getManager();
        $submissions = $submissionManager->find(
            [
                'target_blog_id' => $this->getEntityHelper()->getSiteHelper()->getCurrentBlogId(),
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
            throw new SmartlingDbException('No submission found');
        }
    }

    public function popupIFrame()
    {
        // SmartlingUserCapabilities::SMARTLING_CAPABILITY_MENU_CAP
        //handle save
        $this->renderPage();
    }

    public function extendSubmitBox()
    {
        global $post;
        try {
            $submission = $this->getSubmission($post->ID);
            add_thickbox();
            echo HtmlTagGeneratorHelper::tag(
                'div',
                HtmlTagGeneratorHelper::tag(
                    'a',
                    __('Translation lock'),
                    [
                        'class' => 'thickbox',
                        'href'  => vsprintf(
                            '%s/wp-admin/admin-post.php?'
                            . implode(
                                '&',
                                [
                                    'action=smartling_translation_lock_popup',
                                    'submission=%s',
                                    'TB_iframe=true',
                                    'width=600',
                                    'height=550',
                                ]
                            ),
                            [
                                get_site_url(),
                                $submission->getId(),
                            ]
                        ),
                    ]
                ),
                ['class' => 'misc-pub-section']);
        } catch (SmartlingDbException $e) {
            return;
        }
    }

    private function getTranslationFields(SubmissionEntity $submission)
    {
        $_fields = [];
        foreach ($this->getContentHelper()->readTargetContent($submission)->toArray() as $k => $v) {
            $_fields['entity/' . $k] = $v;
        }
        foreach ($this->getContentHelper()->readTargetMetadata($submission) as $k => $v) {
            $_fields['meta/' . $k] = $v;
        }

        return $_fields;
    }

    private function getLockedFields(SubmissionEntity $submission)
    {
        $_fields = [];
        if (0 < strlen($submission->getLockedFields())) {
            $_fields = maybe_unserialize($submission->getLockedFields());
            $_fields = is_array($_fields) ? $_fields : [];
        }

        return $_fields;
    }

    public function renderPage()
    {
        $submissionId = (int)$this->getQueryParam('submission', 0);
        if (0 < $submissionId) {
            $searchResult = $this->getManager()->findByIds([$submissionId]);
            if (0 < count($searchResult)) {
                /**
                 * @var SubmissionEntity $submission
                 */
                $submission = ArrayHelper::first($searchResult);
                $fields = $this->getTranslationFields($submission);
                $lockedFields = $this->getLockedFields($submission);
                $data = [];
                foreach ($fields as $fielName => $fieldValue) {
                    $data[] = [
                        'name'   => $fielName,
                        'value'  => $fieldValue,
                        'locked' => is_array($lockedFields) && in_array($fielName, $lockedFields),
                    ];
                }
                $table = new TranslationLockTableWidget();
                $table->setData($data);
                $table->prepare_items();
                $this->view(
                    [
                        'table'      => $table,
                        'submission' => $submission,
                    ]
                );
            }
        }
    }
}