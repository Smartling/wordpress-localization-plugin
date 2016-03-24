<?php

namespace Smartling\WP\Controller;

use Smartling\Base\SmartlingCore;
use Smartling\Bootstrap;
use Smartling\Exception\SmartlingDbException;
use Smartling\Exception\SmartlingNotSupportedContentException;
use Smartling\Helpers\CommonLogMessagesTrait;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Jobs\DownloadTranslationJob;
use Smartling\Jobs\UploadJob;
use Smartling\Queue\Queue;
use Smartling\WP\WPAbstract;
use Smartling\WP\WPHookInterface;

/**
 * Class TaxonomyWidgetController
 * @package Smartling\WP\Controller
 */
class TaxonomyWidgetController extends WPAbstract implements WPHookInterface
{

    const WIDGET_DATA_NAME = 'smartling_taxonomy_widget';

    protected $noOriginalFound = 'No original %s found';

    use CommonLogMessagesTrait;

    /**
     * @inheritdoc
     */
    public function register()
    {
        if (!DiagnosticsHelper::isBlocked()) {
            add_action('admin_init', [$this, 'init']);
        }

    }

    /**
     * block initialization
     */
    public function init()
    {
        if (current_user_can(SmartlingUserCapabilities::SMARTLING_CAPABILITY_WIDGET_CAP)) {
            $taxonomies = get_taxonomies(
                [
                    'public'   => true,
                    '_builtin' => true,
                ],
                'names',
                'and'
            );

            if ($taxonomies) {
                foreach ($taxonomies as $taxonomy) {
                    add_action("{$taxonomy}_edit_form", [$this, 'preView'], 100, 1);
                    add_action("edited_{$taxonomy}", [$this, 'save'], 10, 1);
                }
            }
        }
    }

    /**
     * @param string $wordpressType
     *
     * @return string
     * @throws \Smartling\Exception\SmartlingDirectRunRuntimeException
     * @throws SmartlingNotSupportedContentException
     */
    private function getInternalType($wordpressType)
    {
        $reverseMap = WordpressContentTypeHelper::getReverseMap();

        if (array_key_exists($wordpressType, $reverseMap)) {
            return $reverseMap[$wordpressType];
        } else {
            $message = vsprintf('Tried to translate non supported taxonomy:%s', [$wordpressType]);

            $this->getLogger()
                 ->warning($message);

            throw new SmartlingNotSupportedContentException($message);
        }
    }

    /**
     * @param $term
     */
    public function preView($term)
    {
        $taxonomyType = $term->taxonomy;
        try {
            if (current_user_can('publish_posts') && $this->getInternalType($taxonomyType)) {
                $curBlogId = $this->getEntityHelper()->getSiteHelper()->getCurrentBlogId();
                $applicableProfiles = $this->getEntityHelper()->getSettingsManager()
                    ->findEntityByMainLocale($curBlogId);

                if (0 < count($applicableProfiles)) {
                    $submissions = $this->getManager()
                        ->find([
                                   'source_blog_id' => $curBlogId,
                                   'source_id'      => $term->term_id,
                                   'content_type'   => $taxonomyType,
                               ]);

                    $this->view([
                                    'submissions' => $submissions,
                                    'term'        => $term,
                                    'profile'     => reset($applicableProfiles),
                                ]
                    );
                } else {
                    echo HtmlTagGeneratorHelper::tag('p', __('No suitable configuration profile found.'));
                }

            }
        } catch (SmartlingNotSupportedContentException $e) {
            // do not display if not supported yet
        } catch (SmartlingDbException $e) {
            $message = 'Failed to search for the original taxonomy. No source taxonomy found for blog %s, taxonomy_id %s. Hiding widget';
            $this->getLogger()
                ->warning(vsprintf($message, [$this->getEntityHelper()->getSiteHelper()->getCurrentBlogId(),
                                              $term->term_id,]));
        }
    }

    public function save($term_id)
    {
        if (!array_key_exists('taxonomy', $_POST)) {
            return;
        }
        $termType = $_POST['taxonomy'];

        if (!in_array($termType, WordpressContentTypeHelper::getSupportedTaxonomyTypes())) {
            return;
        }

        remove_action("edited_{$termType}", [$this, 'save']);

        if (!isset($_POST[self::WIDGET_DATA_NAME])) {
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
            $core = Bootstrap::getContainer()
                ->get('entrypoint');

            if (count($locales) > 0) {
                $curBlogId = $this->getEntityHelper()
                    ->getSiteHelper()
                    ->getCurrentBlogId();

                switch ($_POST['sub']) {
                    case 'upload':
                        if (0 < count($locales)) {
                            foreach ($locales as $blogId => $blogName) {
                                $result =
                                    $core->createForTranslation(
                                        $termType,
                                        $curBlogId,
                                        $term_id,
                                        (int)$blogId,
                                        $this->getEntityHelper()->getTarget($term_id, $blogId, $termType)
                                    );

                                    $this->getLogger()->info(vsprintf(
                                                                 self::$MSG_UPLOAD_ENQUEUE_ENTITY,
                                                                 [
                                                                     $termType,
                                                                     $curBlogId,
                                                                     $term_id,
                                                                     (int)$blogId,
                                                                     $result->getTargetLocale(),
                                                                 ]
                                                             ));
                            }
                            do_action(UploadJob::JOB_HOOK_NAME);
                        }
                        break;
                    case 'download':
                        $submissions = $this->getManager()->find(
                            [
                                'source_blog_id' => $curBlogId,
                                'source_id'      => $term_id,
                                'content_type'   => $termType,
                            ]
                        );
                        if (0 < count($submissions)) {
                            foreach ($submissions as $submission) {
                                $this->getLogger()->info(vsprintf(
                                    self::$MSG_DOWNLOAD_ENQUEUE_ENTITY,
                                    [
                                        $submission->getId(),
                                        $submission->getStatus(),
                                        $termType,
                                        $curBlogId,
                                        $term_id,
                                        $submission->getTargetBlogId(),
                                        $submission->getTargetLocale()
                                    ]));
                                $core->getQueue()
                                    ->enqueue($submission->toArray(false), Queue::QUEUE_NAME_DOWNLOAD_QUEUE);
                            }
                            do_action(DownloadTranslationJob::JOB_HOOK_NAME);
                        }
                        break;
                }
            }
        }
        add_action("edited_{$termType}", [$this, 'save']);
    }
}
