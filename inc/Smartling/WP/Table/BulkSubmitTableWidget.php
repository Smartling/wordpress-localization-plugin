<?php

namespace Smartling\WP\Table;

use DateTime;
use Smartling\Base\SmartlingCore;
use Smartling\Bootstrap;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\CommonLogMessagesTrait;
use Smartling\Helpers\DateTimeHelper;
use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\PluginInfo;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\StringHelper;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Jobs\JobEntityWithBatchUid;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\WP\Controller\SmartlingListTable;

class BulkSubmitTableWidget extends SmartlingListTable
{
    use CommonLogMessagesTrait;
    use LoggerSafeTrait;

    private const CUSTOM_CONTROLS_NAMESPACE = 'smartling-bulk-submit-page';

    /**
     * base name of Content-type filtering select
     */
    private const CONTENT_TYPE_SELECT_ELEMENT_NAME = 'content-type';

    /**
     * base name of title search textbox
     */
    private const TITLE_SEARCH_TEXTBOX_ELEMENT_NAME = 'title-search';

    /**
     * default values of custom form elements on page
     * @var array
     */
    private $defaultValues = [
        self::CONTENT_TYPE_SELECT_ELEMENT_NAME  => 'post',
        self::TITLE_SEARCH_TEXTBOX_ELEMENT_NAME => '',
    ];

    private $_settings = [
        'singular' => 'submission',
        'plural'   => 'submissions',
        'ajax'     => false,
    ];

    private ConfigurationProfileEntity $profile;
    private LocalizationPluginProxyInterface $localizationPluginProxy;
    private PluginInfo $pluginInfo;
    private SiteHelper $siteHelper;
    private SmartlingCore $core;
    private SubmissionManager $manager;

    public function getProfile(): ConfigurationProfileEntity
    {
        return $this->profile;
    }

    public function __construct(
        LocalizationPluginProxyInterface $localizationPluginProxy,
        SiteHelper $siteHelper,
        SmartlingCore $core,
        SubmissionManager $manager,
        PluginInfo $pluginInfo,
        ConfigurationProfileEntity $profile
    )
    {
        $this->core = $core;
        $this->localizationPluginProxy = $localizationPluginProxy;
        $this->manager = $manager;
        $this->setSource($_REQUEST);
        $this->pluginInfo = $pluginInfo;
        $this->profile = $profile;
        $this->siteHelper = $siteHelper;

        /*
         * Set default content type to first known
         */
        $this->defaultValues[static::CONTENT_TYPE_SELECT_ELEMENT_NAME] =
            ArrayHelper::first(array_keys($this->getFilteredAllowedTypes()));

        parent::__construct($this->_settings);
    }

    /**
     * @return SubmissionManager
     */
    public function getManager()
    {
        return $this->manager;
    }

    /**
     * @return PluginInfo
     */
    public function getPluginInfo()
    {
        return $this->pluginInfo;
    }

    /**
     * @param string $fieldNameKey
     * @param string $orderDirectionKey
     *
     * @return array
     */
    public function getSortingOptions($fieldNameKey = 'orderby', $orderDirectionKey = 'order')
    {
        $column = $this->getFromSource($fieldNameKey, false);
        $direction = 'ASC';

        if (false !== $column) {
            $direction = strtoupper($this->getFromSource($orderDirectionKey,
                                                         SmartlingToCMSDatabaseAccessWrapperInterface::SORT_OPTION_ASC));
        }

        return [
            'orderby' => $column,
            'order'   => $direction,
        ];
    }

    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            default:
                return $item[$column_name];
        }
    }

    /**
     * Generates a checkbox for a row to add row to bulk actions
     *
     * @param array $item
     *
     * @return string
     */
    public function column_cb($item)
    {

        $t = vsprintf('%s-%s', [$item['id'], $item['type']]);

        return HtmlTagGeneratorHelper::tag('input', '', [
            'type'  => 'checkbox',
            'name'  => $this->buildHtmlTagName($this->_args['singular']) . '[]',
            'value' => $t,
            'id'    => $t,
            'class' => 'bulkaction',
        ]);
    }

    /**
     * @inheritdoc
     */
    public function get_columns()
    {
        return [
            'bulkActionCb' => HtmlTagGeneratorHelper::tag(
                'input',
                '',
                [
                    'type'  => 'checkbox',
                    'class' => 'checkall',
                ]
            ),
            'id'           => __('ID'),
            'title'        => __('Title'),
            'author'       => __('Author'),
            'status'       => __('Status'),
            'locales'      => __('Locales'),
            'updated'      => __('Updated'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function get_sortable_columns()
    {

        $fields = [
            'title',
            'status',
            'author',
            'updated',
        ];

        $sortable_columns = [];

        foreach ($fields as $field) {
            $sortable_columns[$field] = [$field, false];
        }

        return $sortable_columns;
    }

    /**
     * @inheritdoc
     */
    public function get_bulk_actions()
    {
        return [];
    }

    /**
     * Handles actions for multiply objects
     */
    private function processBulkAction()
    {
        /**
         * @var array $submissions
         */
        $action = $this->getFromSource('action', 'send');
        $submissions = $this->getFormElementValue('submission', []);
        $locales = [];
        $batchUid = '';
        $data = $this->getFromSource('bulk-submit-locales', []);
        $jobName = '';
        $smartlingData = [];
        $profile = $this->getProfile();

        if ($action === 'send') {
            $smartlingData = $this->getFromSource('smartling', []);
            if (empty($smartlingData)) {
                return;
            }

            if (!empty($smartlingData['locales'])) {
                foreach (explode(',', $smartlingData['locales']) as $localeId) {
                    $data['locales'][$localeId]['enabled'] = 'on';
                }
            }

            $wrapper = Bootstrap::getContainer()->get('api.wrapper.with.retries');

            try {
                $jobName = $smartlingData['jobName'];
                $batchUid = $wrapper->retrieveBatch($profile, $smartlingData['jobId'],
                    'true' === $smartlingData['authorize'], [
                        'name' => $jobName,
                        'description' => $smartlingData['jobDescription'],
                        'dueDate' => [
                            'date' => $smartlingData['jobDueDate'],
                            'timezone' => $smartlingData['timezone'],
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
        }

        if (null !== $data && array_key_exists('locales', $data)) {
            foreach ($data['locales'] as $blogId => $blogName) {
                if (array_key_exists('enabled', $blogName) && 'on' === $blogName['enabled']) {
                    $locales[$blogId] = $blogName['locale'];
                }
            }

            if (is_array($submissions) && count($locales) > 0) {
                $clone = 'clone' === $action;
                foreach ($submissions as $submission) {
                    [$id] = explode('-', $submission);
                    $type = $this->getContentTypeFilterValue();
                    $curBlogId = $this->getProfile()->getOriginalBlogId()->getBlogId();
                    foreach ($locales as $blogId => $blogName) {
                        $submissionEntity = $this->core->createForTranslation($type, $curBlogId, $id, (int)$blogId, new JobEntityWithBatchUid($batchUid, $jobName, $clone ? '' : $smartlingData['jobId'], $profile->getProjectId()), $clone);

                        $this->getLogger()
                            ->info(vsprintf(
                                       static::$MSG_UPLOAD_ENQUEUE_ENTITY,
                                       [
                                           $type,
                                           $curBlogId,
                                           $id,
                                           $blogId,
                                           $submissionEntity->getTargetLocale(),
                                       ]
                                   ));
                    }
                }
            }
        }
    }

    /**
     * Handles actions
     */
    private function processAction()
    {
        $this->processBulkAction();
    }

    /**
     * @return string|null
     */
    private function getContentTypeFilterValue()
    {
        $value = $this->getFormElementValue(
            self::CONTENT_TYPE_SELECT_ELEMENT_NAME,
            $this->defaultValues[self::CONTENT_TYPE_SELECT_ELEMENT_NAME]
        );

        return 'any' === $value ? null : $value;
    }

    /**
     * @return string|null
     */
    private function getTitleSearchTextFilterValue()
    {
        $value = $this->getFormElementValue(
            self::TITLE_SEARCH_TEXTBOX_ELEMENT_NAME,
            $this->defaultValues[self::TITLE_SEARCH_TEXTBOX_ELEMENT_NAME]
        );

        return $value;
    }

    /**
     * @inheritdoc
     */
    public function prepare_items()
    {
        $pageOptions = [
            'limit' => $this->manager->getPageSize(),
            'page'  => $this->get_pagenum(),
        ];

        $this->_column_headers = [
            $this->get_columns(),
            ['id'],
            $this->get_sortable_columns(),
        ];
        $this->processAction();

        $contentTypeFilterValue = $this->getContentTypeFilterValue();
        $sortOptions = $this->getSortingOptions();

        $io = $this->core->getContentIoFactory()->getMapper($contentTypeFilterValue);

        $searchString = $this->getTitleSearchTextFilterValue();

        $data = $io->getAll(
            $pageOptions['limit'],
            ($pageOptions['page'] - 1) * $pageOptions['limit'],
            $sortOptions['orderby'],
            $sortOptions['order'],
            $searchString
        );


        $total = $io->getTotal();

        $dataAsArray = [];
        if ($data) {
            foreach ($data as $item) {
                $row = $item->toBulkSubmitScreenRow()->toArray();
                $entities = $this->getManager()->find([
                    SubmissionEntity::FIELD_SOURCE_BLOG_ID => $this->siteHelper->getCurrentBlogId(),
                    SubmissionEntity::FIELD_SOURCE_ID => $row['id'],
                    SubmissionEntity::FIELD_CONTENT_TYPE => $this->getContentTypeFilterValue(),
                ]);

                if (count($entities) > 0) {
                    $locales = [];
                    foreach ($entities as $entity) {
                        $locales[] =
                            $this->localizationPluginProxy->getBlogNameByLocale($entity->getTargetLocale());
                    }

                    $row['locales'] = implode(', ', $locales);
                }

                $file_uri_max_chars = 50;
                if (mb_strlen($row['title'], 'utf8') > $file_uri_max_chars) {
                    $orig = $row['title'];
                    $shrinked = mb_substr($orig, 0, $file_uri_max_chars - 3, 'utf8') . '...';

                    $row['title'] = HtmlTagGeneratorHelper::tag('span', $shrinked, ['title' => $orig]);
                }

                $updatedDate = '';
                if (!StringHelper::isNullOrEmpty($row['updated'])) {
                    $dt = DateTimeHelper::stringToDateTime($row['updated']);
                    if ($dt instanceof DateTime) {
                        $updatedDate = DateTimeHelper::toWordpressLocalDateTime($dt);
                    }
                }

                $row['updated'] = $updatedDate;
                $row['bulkActionCb'] = $this->column_cb($row);
                $dataAsArray[] = $row;
            }
        }


        $this->items = $dataAsArray;

        $this->set_pagination_args([
                                       'total_items' => $total,
                                       'per_page'    => $pageOptions['limit'],
                                       'total_pages' => ceil($total / $pageOptions['limit']),
                                   ]);
    }

    private function getFilteredAllowedTypes()
    {
        $types = $this->getActiveContentTypes($this->siteHelper, 'bulkSubmit');

        $restrictedTypes = WordpressContentTypeHelper::getTypesRestrictedToBulkSubmit();

        $typesFiltered = [];

        foreach ($types as $value => $title) {
            if (in_array($value, $restrictedTypes, true)) {
                continue;
            }
            $typesFiltered[$value] = $title;
        }

        return $typesFiltered;
    }

    /**
     * @return string
     */
    public function contentTypeSelectRender()
    {
        $controlName = self::CONTENT_TYPE_SELECT_ELEMENT_NAME;
        $typesFiltered = $this->getFilteredAllowedTypes();

        $value = $this->getFormElementValue(
            $controlName,
            $this->defaultValues[$controlName]
        );

        $html = HtmlTagGeneratorHelper::tag(
                'label',
                __('Type'),
                [
                    'for' => $this->buildHtmlTagName($controlName),
                ]
            ) . HtmlTagGeneratorHelper::tag(
                'select',
                HtmlTagGeneratorHelper::renderSelectOptions(
                    $value,
                    $typesFiltered
                ),
                [
                    'id'   => $this->buildHtmlTagName($controlName),
                    'name' => $this->buildHtmlTagName($controlName),
                ]
            );

        return $html;
    }

    /**
     * @return string
     */
    public function titleFilterRender()
    {
        $controlName = self::TITLE_SEARCH_TEXTBOX_ELEMENT_NAME;

        $value = $this->getFormElementValue(
            $controlName,
            $this->defaultValues[$controlName]
        );

        $html = HtmlTagGeneratorHelper::tag(
                'label',
                __('Title Contains'),
                [
                    'for' => $this->buildHtmlTagName($controlName),
                ]
            ) . HtmlTagGeneratorHelper::tag(
                'input',
                '',
                [
                    'type'  => 'text',
                    'id'    => $this->buildHtmlTagName($controlName),
                    'name'  => $this->buildHtmlTagName($controlName),
                    'value' => $value,
                ]
            );

        return $html;
    }

    /**
     * Renders button
     *
     * @param $label
     *
     * @return string
     */
    public function renderSubmitButton($label)
    {
        $id = $name = $this->buildHtmlTagName('go-and-filter');

        $options = [
            'type'  => 'submit',
            'id'    => $id,
            //'name'  => $name,
            'class' => 'button action',
            'value' => __($label),

        ];

        return HtmlTagGeneratorHelper::tag('input', '', $options);
    }

    /**
     * Retrieves from source array value for input element
     *
     * @param string $name
     * @param mixed  $defaultValue
     *
     * @return mixed
     */
    private function getFormElementValue($name, $defaultValue)
    {
        return $this->getFromSource($this->buildHtmlTagName($name), $defaultValue);
    }

    /**
     * Builds unique name attribute value for HTML Form element tag
     *
     * @param string $name
     *
     * @return string
     */
    private function buildHtmlTagName($name)
    {
        return self::CUSTOM_CONTROLS_NAMESPACE . '-' . $name;
    }
}
