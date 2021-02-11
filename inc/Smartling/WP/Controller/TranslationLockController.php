<?php

namespace Smartling\WP\Controller;

use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Exception\SmartlingDbException;
use Smartling\Exception\SmartlingDirectRunRuntimeException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\Cache;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\EntityHelper;
use Smartling\Helpers\GutenbergBlockHelper;
use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Helpers\PluginInfo;
use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\WP\Table\TranslationLockTableWidget;
use Smartling\WP\WPAbstract;
use Smartling\WP\WPHookInterface;

class TranslationLockController extends WPAbstract implements WPHookInterface
{
    private $blockHelper;
    private $contentHelper;

    public function __construct(
        LocalizationPluginProxyInterface $connector,
        PluginInfo $pluginInfo,
        EntityHelper $entityHelper,
        SubmissionManager $manager,
        Cache $cache,
        ContentHelper $contentHelper,
        GutenbergBlockHelper $blockHelper
    ) {
        parent::__construct($connector, $pluginInfo, $entityHelper, $manager, $cache);
        $this->blockHelper = $blockHelper;
        $this->contentHelper = $contentHelper;
    }

    /**
     * Registers wp hook handlers. Invoked by wordpress.
     * @return void
     */
    public function register()
    {
        if (!DiagnosticsHelper::isBlocked()) {
            add_action('add_meta_boxes', [$this, 'boxRegister'], 10, 2);
            add_action('admin_post_smartling_translation_lock_popup', [$this, 'popupIFrame']);
        }
    }

    /**
     * @param string $post_type
     * @param \WP_Post $post
     * @noinspection PhpUnusedParameterInspection
     */
    public function boxRegister($post_type, $post)
    {
        try {
            if (current_user_can(SmartlingUserCapabilities::SMARTLING_CAPABILITY_WIDGET_CAP)
                && $submission = $this->getSubmission($post->ID)) {
                add_meta_box(
                    'translation_lock',
                    __('Translation Lock'),
                    [$this, 'boxRender'],
                    null,
                    'side',
                    'high'
                );
            }

        } catch (\Exception $e) {
        }


    }

    /**
     * @param \WP_Post $post
     * @throws SmartlingDirectRunRuntimeException
     */
    public function boxRender($post)
    {
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
                        'href' => vsprintf(
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

    /**
     * @param $postId
     *
     * @return SubmissionEntity
     * @throws SmartlingDbException
     * @throws SmartlingDirectRunRuntimeException
     */
    private function getSubmission($postId)
    {
        $submissionManager = $this->getManager();
        $submissions = $submissionManager->find(
            [
                SubmissionEntity::FIELD_TARGET_BLOG_ID => $this->getEntityHelper()->getSiteHelper()->getCurrentBlogId(),
                SubmissionEntity::FIELD_TARGET_ID      => $postId,
            ]
        );

        if (0 < count($submissions)) {
            return ArrayHelper::first($submissions);
        }

        throw new SmartlingDbException('No submission found');
    }

    public function popupIFrame()
    {
        $user = wp_get_current_user();
        if (!($user instanceof \WP_User)) {
            return;
        }

        if (!user_can($user, SmartlingUserCapabilities::SMARTLING_CAPABILITY_WIDGET_CAP)) {
            $this->notAllowed();

            return;
        }

        if (0 < count($_POST)) {
            $this->handleFormPost();
        }
        $this->renderPage();
    }

    public function handleFormPost()
    {
        $_pageLock = array_key_exists('lock_page', $_POST) ? 1 : 0;
        $_lockedFields =
            array_key_exists('lockField', $_POST) && is_array($_POST['lockField'])
                ? array_keys($_POST['lockField'])
                : [];

        $submission = $this->getSubmissionFromQuery();
        if (false !== $submission) {
            $submission->setLockedFields(serialize($_lockedFields));
            $submission->setIsLocked($_pageLock);
            $this->getManager()->storeEntity($submission);
        }
    }

    public function notAllowed()
    {
        echo "Sorry, you're not allowed to go here.";
    }

    /**
     * @param SubmissionEntity $submission
     * @return array
     */
    private function getTranslationFields(SubmissionEntity $submission)
    {
        $_fields = [];
        foreach ($this->blockHelper->addPostContentBlocks($this->contentHelper->readTargetContent($submission)->toArray()) as $k => $v) {
            $_fields["entity/$k"] = $v;
        }
        foreach ($this->contentHelper->readTargetMetadata($submission) as $k => $v) {
            $_fields['meta/' . $k] = $v;
        }

        ksort($_fields);

        return $_fields;
    }

    /**
     * @return false|SubmissionEntity
     */
    private function getSubmissionFromQuery()
    {
        $submissionId = (int)$this->getQueryParam('submission', 0);
        if (0 < $submissionId) {
            $searchResult = $this->getManager()->findByIds([$submissionId]);
            if (0 < count($searchResult)) {
                /**
                 * @var SubmissionEntity $submission
                 */
                $submission = ArrayHelper::first($searchResult);

                return $submission;
            }
        }

        return false;
    }

    public function renderPage()
    {
        $submission = $this->getSubmissionFromQuery();

        if (false !== $submission) {
            $fields = $this->getTranslationFields($submission);
            $lockedFields = $submission->getLockedFields();
            $data = [];
            foreach ($fields as $fielName => $fieldValue) {
                $data[] = [
                    'name'   => $fielName,
                    'value'  => $fieldValue,
                    'locked' => is_array($lockedFields) && in_array($fielName, $lockedFields, true),
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
