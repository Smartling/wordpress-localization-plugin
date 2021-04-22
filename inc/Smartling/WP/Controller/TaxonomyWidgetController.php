<?php

namespace Smartling\WP\Controller;

use Smartling\Base\SmartlingCore;
use Smartling\Bootstrap;
use Smartling\Exception\SmartlingDbException;
use Smartling\Exception\SmartlingDirectRunRuntimeException;
use Smartling\Exception\SmartlingNotSupportedContentException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\CommonLogMessagesTrait;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Jobs\DownloadTranslationJob;
use Smartling\Jobs\JobInformationEntityWithBatchUid;
use Smartling\Jobs\UploadJob;
use Smartling\Queue\Queue;
use Smartling\Submissions\SubmissionEntity;
use Smartling\WP\WPAbstract;
use Smartling\WP\WPHookInterface;

class TaxonomyWidgetController extends WPAbstract implements WPHookInterface
{

    const WIDGET_DATA_NAME = 'smartling';

    protected $noOriginalFound = 'No original %s found';

    use CommonLogMessagesTrait;
    use DetectContentChangeTrait;

    /**
     * @var SmartlingCore
     */
    private $core;

    /**
     * @var string
     */
    private $taxonomy;

    /**
     * @return string
     */
    public function getTaxonomy()
    {
        return $this->taxonomy;
    }

    /**
     * @param string $taxonomy
     */
    public function setTaxonomy($taxonomy)
    {
        $this->taxonomy = $taxonomy;
    }

    /**
     * @return SmartlingCore
     */
    private function getCore()
    {
        if (!($this->core instanceof SmartlingCore)) {
            $this->core = Bootstrap::getContainer()->get('entrypoint');
        }

        return $this->core;
    }

    public function register(): void
    {
        if (!DiagnosticsHelper::isBlocked()) {
            // already running in scope of 'admin_init', so calling directly
            $this->init();
        }

    }

    /**
     * block initialization
     */
    public function init()
    {
        if (current_user_can(SmartlingUserCapabilities::SMARTLING_CAPABILITY_WIDGET_CAP)) {
            add_action("{$this->getTaxonomy()}_edit_form", [$this, 'preView'], 100, 1);
            add_action("edited_{$this->getTaxonomy()}", [$this, 'save'], 10, 1);
        }
    }

    /**
     * @param string $wordpressType
     *
     * @return string
     * @throws SmartlingDirectRunRuntimeException
     * @throws SmartlingNotSupportedContentException
     */
    private function getInternalType($wordpressType)
    {
        $reverseMap = WordpressContentTypeHelper::getReverseMap();

        if (array_key_exists($wordpressType, $reverseMap)) {
            return $reverseMap[$wordpressType];
        }

        $message = vsprintf('Tried to translate non supported taxonomy:%s', [$wordpressType]);

        $this->getLogger()
            ->warning($message);

        throw new SmartlingNotSupportedContentException($message);
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
                                   SubmissionEntity::FIELD_SOURCE_BLOG_ID => $curBlogId,
                                   SubmissionEntity::FIELD_SOURCE_ID      => $term->term_id,
                                   SubmissionEntity::FIELD_CONTENT_TYPE   => $taxonomyType,
                               ]);

                    $this->view([
                                    'submissions' => $submissions,
                                    'term'        => $term,
                                    'profile'     => ArrayHelper::first($applicableProfiles),
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
        $this->getLogger()->debug("Usage: class='" . __CLASS__ . "', function='" . __FUNCTION__ . "'");
        if (!array_key_exists('taxonomy', $_POST)) {
            return;
        }
        $termType = $_POST['taxonomy'];
        if (!in_array($termType, WordpressContentTypeHelper::getSupportedTaxonomyTypes(), true)) {
            return;
        }
        $sourceBlog = $this->getEntityHelper()->getSiteHelper()->getCurrentBlogId();
        $originalId = (int)$term_id;
        $this->detectChange($sourceBlog, $originalId, $termType);
        remove_action("edited_{$termType}", [$this, 'save']);

        if (!isset($_POST[self::WIDGET_DATA_NAME])) {
            return;
        }

        $data = $_POST[self::WIDGET_DATA_NAME];

        if (null !== $data && array_key_exists('locales', $data)) {
            $locales = [];
            if (is_array($data['locales'])) {
                foreach ($data['locales'] as $_locale) {
                    if (array_key_exists('enabled', $_locale) && 'on' === $_locale['enabled']) {
                        $locales[] = (int)$_locale['blog'];
                    }
                }
            } elseif (is_string($data['locales'])) {
                $locales = explode(',', $data['locales']);
            } else {
                return;
            }
            $core = $this->getCore();
            $translationHelper = $core->getTranslationHelper();

            if (array_key_exists('sub', $_POST) && count($locales) > 0) {
                $curBlogId = $this->getEntityHelper()->getSiteHelper()->getCurrentBlogId();
                switch ($_POST['sub']) {
                    case 'Upload':
                        if (0 < count($locales)) {
                            $wrapper = $this->getCore()->getApiWrapper();
                            $profile = ArrayHelper::first($this->getProfiles());

                            if (!$profile) {
                                $this->getLogger()->error('No suitable configuration profile found.');

                                return;
                            }
                            try {
                                $jobName = $data['jobName'];
                                $batchUid = $wrapper->retrieveBatch($profile, $data['jobId'],
                                    'true' === $data['authorize'], [
                                        'name' => $jobName,
                                        'description' => $data['jobDescription'],
                                        'dueDate' => [
                                            'date' => $data['jobDueDate'],
                                            'timezone' => $data['timezone'],
                                        ],
                                    ]);
                            } catch (\Exception $e) {
                                $this
                                    ->getLogger()
                                    ->error(
                                        vsprintf(
                                            'Failed retrieving batch for job %s. Translation aborted.',
                                            [
                                                var_export($_POST['jobId'], true),
                                            ]
                                        )
                                    );
                                return;
                            }
                            $jobInfo = new JobInformationEntityWithBatchUid($batchUid, $jobName, $data['jobId'], $profile->getProjectId());
                            foreach ($locales as $blogId) {
                                if ($translationHelper->isRelatedSubmissionCreationNeeded($this->getTaxonomy(), $sourceBlog, $originalId, (int)$blogId)) {
                                    $submission = $translationHelper->tryPrepareRelatedContent($this->getTaxonomy(), $sourceBlog, $originalId, (int)$blogId, $jobInfo);
                                } else {
                                    $submission = $translationHelper->getExistingSubmissionOrCreateNew($this->getTaxonomy(), $sourceBlog, $originalId, (int)$blogId, $jobInfo);
                                }

                                if (0 < $submission->getId()) {
                                    $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_NEW);
                                    $submission->setBatchUid($jobInfo->getBatchUid());
                                    $submission->setJobInfo($jobInfo->getJobInformationEntity());
                                    $submission = $core->getSubmissionManager()->storeEntity($submission);
                                }

                                $this->getLogger()->info(
                                    vsprintf(
                                        static::$MSG_UPLOAD_ENQUEUE_ENTITY_JOB,
                                        [
                                            $this->getTaxonomy(),
                                            $sourceBlog,
                                            $originalId,
                                            (int)$blogId,
                                            $submission->getTargetLocale(),
                                            $data['jobId'],
                                            $submission->getBatchUid(),
                                        ]
                                    ));
                            }

                            $this->getLogger()->debug('Triggering Upload Job.');
                            do_action(UploadJob::JOB_HOOK_NAME);
                        }
                        break;
                    case 'Download':
                        $submissions = $this->getManager()->find(
                            [
                                SubmissionEntity::FIELD_SOURCE_BLOG_ID => $curBlogId,
                                SubmissionEntity::FIELD_SOURCE_ID      => $term_id,
                                SubmissionEntity::FIELD_CONTENT_TYPE   => $termType,
                            ]
                        );
                        if (0 < count($submissions)) {
                            foreach ($submissions as $submission) {
                                $this->getLogger()->info(vsprintf(
                                                             static::$MSG_DOWNLOAD_ENQUEUE_ENTITY,
                                                             [
                                                                 $submission->getId(),
                                                                 $submission->getStatus(),
                                                                 $termType,
                                                                 $curBlogId,
                                                                 $term_id,
                                                                 $submission->getTargetBlogId(),
                                                                 $submission->getTargetLocale(),
                                                             ]));
                                $this->getCore()->getQueue()
                                    ->enqueue([$submission->getId()], Queue::QUEUE_NAME_DOWNLOAD_QUEUE);
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
