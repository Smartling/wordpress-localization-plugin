<?php

namespace Smartling\WP\Controller;

use Exception;
use Smartling\Base\SmartlingCore;
use Smartling\Bootstrap;
use Smartling\Exception\SmartlingDbException;
use Smartling\Helpers\CommonLogMessagesTrait;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Jobs\DownloadTranslationJob;
use Smartling\Jobs\UploadJob;
use Smartling\Queue\Queue;
use Smartling\WP\WPAbstract;
use Smartling\WP\WPHookInterface;

/**
 * Class PostWidgetController
 * @package Smartling\WP\Controller
 */
class PostWidgetController extends WPAbstract implements WPHookInterface
{

    const WIDGET_NAME = 'smartling_connector_widget';

    const WIDGET_DATA_NAME = 'smartling_post_based_widget';

    const CONNECTOR_NONCE = 'smartling_connector_nonce';

    protected $servedContentType = WordpressContentTypeHelper::CONTENT_TYPE_POST;

    protected $needSave = 'Need to have title';

    protected $noOriginalFound = 'No original post found';

    use CommonLogMessagesTrait;

    /**
     * @inheritdoc
     */
    public function register()
    {
        if (!DiagnosticsHelper::isBlocked()) {
            add_action('add_meta_boxes', [$this, 'box']);
            add_action('save_post', [$this, 'save']);
        }
    }

    /**
     * add_meta_boxes hook
     *
     * @param string $post_type
     */
    public function box($post_type)
    {
        $post_types = [$this->servedContentType];
        if (in_array($post_type, $post_types)
            && current_user_can(SmartlingUserCapabilities::SMARTLING_CAPABILITY_WIDGET_CAP)
        ) {
            add_meta_box(
                self::WIDGET_NAME,
                __('Smartling Post Widget'),
                [$this, 'preView'],
                $post_type,
                'side',
                'high'
            );
        }
    }

    /**
     * @param $post
     */
    public function preView($post)
    {
        wp_nonce_field(self::WIDGET_NAME, self::CONNECTOR_NONCE);
        if ($post && $post->post_title && '' !== $post->post_title) {
            try {
                $eh = $this->getEntityHelper();

                $currentBlogId = $eh->getSiteHelper()
                    ->getCurrentBlogId();

                $profile = $eh
                    ->getSettingsManager()
                    ->findEntityByMainLocale(
                        $currentBlogId
                    );

                if (0 < count($profile)) {
                    $submissions = $this->getManager()
                        ->find([
                                   'source_blog_id' => $this->getEntityHelper()->getSiteHelper()->getCurrentBlogId(),
                                   'source_id'      => $post->ID,
                                   'content_type'   => $this->servedContentType,
                               ]);

                    $this->view([
                                    'submissions' => $submissions,
                                    'post'        => $post,
                                    'profile'     => reset($profile),
                                ]
                    );
                } else {
                    echo '<p>' . __('No suitable configuration profile found.') . '</p>';
                }


            } catch (SmartlingDbException $e) {
                $message = 'Failed to search for the original post. No source post found for blog %s, post %s. Hiding widget';
                $this->getLogger()
                    ->warning(
                        vsprintf($message, [
                            $this->getEntityHelper()
                                ->getSiteHelper()
                                ->getCurrentBlogId(),
                            $post->ID,
                        ])
                    );
                echo '<p>' . __($this->noOriginalFound) . '</p>';
            } catch (\Exception $e) {
                $this->getLogger()
                    ->error($e->getMessage() . '[' . $e->getFile() . ':' . $e->getLine() . ']');
            }
        } else {
            echo '<p>' . __($this->needSave) . '</p>';
        }
    }

    /**
     * @param $post_id
     *
     * @return bool
     */
    private function runValidation($post_id)
    {
        if (!array_key_exists(self::CONNECTOR_NONCE, $_POST)) {
            return false;
        }

        $nonce = $_POST[self::CONNECTOR_NONCE];

        if (!wp_verify_nonce($nonce, self::WIDGET_NAME)) {
            return false;
        }

        if (defined('DOING_AUTOSAVE') && true === DOING_AUTOSAVE) {
            return false;
        }

        if ($this->servedContentType !== $_POST['post_type']) {
            return false;
        }

        return $this->isAllowedToSave($post_id);
    }

    /**
     * @param $post_id
     *
     * @return bool
     */
    protected function isAllowedToSave($post_id)
    {
        return current_user_can('edit_post', $post_id);
    }

    /**
     * @param $post_id
     *
     * @return mixed
     */
    public function save($post_id)
    {

        if (wp_is_post_revision($post_id)) {
            return;
        }

        remove_action('save_post', [$this, 'save']);

        if (false === $this->runValidation($post_id)) {
            return $post_id;
        }

        if (!array_key_exists(self::WIDGET_DATA_NAME, $_POST)) {
            return;
        }

        $data = $_POST[self::WIDGET_DATA_NAME];

        $locales = [];

        if (null !== $data && array_key_exists('locales', $data)) {

            foreach ($data['locales'] as $blogId => $blogName) {
                if (array_key_exists('enabled', $blogName) && 'on' === $blogName['enabled']) {
                    $locales[$blogId] = $blogName['locale'];
                }
            }

            /**
             * @var SmartlingCore $core
             */
            $core = Bootstrap::getContainer()->get('entrypoint');

            if (count($locales) > 0) {
                switch ($_POST['sub']) {
                    case 'upload':
                        $sourceBlog = $this->getEntityHelper()->getSiteHelper()->getCurrentBlogId();
                        $originalId = (int)$post_id;
                        if (0 < count($locales)) {
                            foreach ($locales as $blogId => $blogName) {
                                $result = $core->createForTranslation(
                                    $this->servedContentType,
                                    $sourceBlog,
                                    $originalId,
                                    (int)$blogId,
                                    $this->getEntityHelper()->getTarget($originalId, $blogId)
                                );

                                $this->getLogger()->info(vsprintf(
                                                             self::$MSG_UPLOAD_ENQUEUE_ENTITY,
                                                             [
                                                                 $this->servedContentType,
                                                                 $sourceBlog,
                                                                 $originalId,
                                                                 (int)$blogId,
                                                                 $result->getTargetLocale(),
                                                             ]
                                                         ));
                            }
                            do_action(UploadJob::JOB_HOOK_NAME);
                        }

                        break;
                    case 'download':
                        $sourceBlog = $this->getEntityHelper()->getSiteHelper()->getCurrentBlogId();
                        $originalId = (int)$post_id;

                        $targetLocaleIds = array_keys($locales);

                        foreach ($targetLocaleIds as $targetBlogId) {
                            $submissions = $this->getManager()
                                ->find(
                                    [
                                        'source_id'      => $originalId,
                                        'source_blog_id' => $sourceBlog,
                                        'content_type'   => $this->servedContentType,
                                        'target_blog_id' => $targetBlogId,
                                    ]
                                );

                            if (0 < count($submissions)) {
                                $submission = reset($submissions);

                                $this->getLogger()
                                    ->info(
                                        vsprintf(
                                            self::$MSG_DOWNLOAD_ENQUEUE_ENTITY,
                                            [
                                                $submission->getId(),
                                                $submission->getStatus(),
                                                $this->servedContentType,
                                                $sourceBlog,
                                                $originalId,
                                                $submission->getTargetBlogId(),
                                                $submission->getTargetLocale(),
                                            ]
                                        )
                                    );

                                $core->getQueue()->enqueue([$submission->getId()], Queue::QUEUE_NAME_DOWNLOAD_QUEUE);
                            }
                        }

                        do_action(DownloadTranslationJob::JOB_HOOK_NAME);   
                        break;
                }
            }
        }
        add_action('save_post', [$this, 'save']);
    }
}
