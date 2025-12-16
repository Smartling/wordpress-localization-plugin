<?php

namespace Smartling\WP\Controller;

use Smartling\Base\SmartlingCore;
use Smartling\Exception\SmartlingDbException;
use Smartling\Exception\SmartlingDirectRunRuntimeException;
use Smartling\Exception\SmartlingNotSupportedContentException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\CommonLogMessagesTrait;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\WP\WPAbstract;
use Smartling\WP\WPHookInterface;

class TaxonomyWidgetController extends WPAbstract implements WPHookInterface
{
    public const WIDGET_DATA_NAME = 'smartling';

    use CommonLogMessagesTrait;
    use DetectContentChangeTrait;

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
                $curBlogId = $this->siteHelper->getCurrentBlogId();
                $applicableProfiles = $this->settingsManager->findEntityByMainLocale($curBlogId);

                if (0 < count($applicableProfiles)) {
                    $submissions = $this->submissionManager
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
                ->warning(sprintf($message, $this->siteHelper->getCurrentBlogId(), $term->term_id));
        }
    }

    public function save($term_id)
    {
        if (!array_key_exists('taxonomy', $_POST)) {
            return;
        }
        $termType = $_POST['taxonomy'];
        if (!in_array($termType, WordpressContentTypeHelper::getSupportedTaxonomyTypes(), true)) {
            return;
        }
        $sourceBlog = $this->siteHelper->getCurrentBlogId();
        $originalId = (int)$term_id;
        $this->detectChange($sourceBlog, $originalId, $termType);
        remove_action("edited_{$termType}", [$this, 'save']);

        if (!isset($_POST[self::WIDGET_DATA_NAME])) {
            return;
        }

        if (($_POST[self::WIDGET_DATA_NAME]['locales'] ?? null) !== null) {
            $this->getLogger()->warning("Download or upload skipped. Revert code to revision 01ea5f0928d39b3fb0fad0d3c1372330c0555b96");
        }
        add_action("edited_{$termType}", [$this, 'save']);
    }
}
