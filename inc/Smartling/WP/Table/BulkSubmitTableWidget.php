<?php

namespace Smartling\WP\Table;

use DateTime;
use JetBrains\PhpStorm\ArrayShape;
use Smartling\ApiWrapperInterface;
use Smartling\Base\ExportedAPI;
use Smartling\Base\SmartlingCore;
use Smartling\Bootstrap;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\DbAl\UploadQueueManager;
use Smartling\Exception\BlogNotFoundException;
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
use Smartling\Models\IntegerIterator;
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
    private const SUBMISSION_STATUS_SELECT_ELEMENT_NAME = 'submission-status';
    /**
     * base name of title search textbox
     */
    private const TITLE_SEARCH_TEXTBOX_ELEMENT_NAME = 'title-search';

    private array $defaultValues = [
        self::CONTENT_TYPE_SELECT_ELEMENT_NAME  => 'post',
        self::SUBMISSION_STATUS_SELECT_ELEMENT_NAME => 'All',
        self::TITLE_SEARCH_TEXTBOX_ELEMENT_NAME => '',
    ];

    private array $_settings = [
        'singular' => 'submission',
        'plural'   => 'submissions',
        'ajax'     => false,
    ];

    private bool $dataFiltered = false;

    protected PluginInfo $pluginInfo;

    public function getProfile(): ConfigurationProfileEntity
    {
        return $this->profile;
    }

    public function __construct(
        private ApiWrapperInterface $apiWrapper,
        protected LocalizationPluginProxyInterface $localizationPluginProxy,
        protected SiteHelper $siteHelper,
        protected SmartlingCore $core,
        protected SubmissionManager $manager,
        protected UploadQueueManager $uploadQueueManager,
        protected ConfigurationProfileEntity $profile,
    ) {
        $this->setSource($_REQUEST);

        $filteredAllowedTypes = $this->getFilteredAllowedTypes();
        $this->defaultValues[static::CONTENT_TYPE_SELECT_ELEMENT_NAME] =
            array_key_exists('post', $filteredAllowedTypes) ? 'post' :
                ArrayHelper::first(array_keys($filteredAllowedTypes));

        parent::__construct($this->_settings);
    }

    public function isDataFiltered(): bool
    {
        return $this->dataFiltered;
    }

    #[ArrayShape(['orderby' => 'string', 'order' => 'string'])]
    public function getSortingOptions(string $fieldNameKey = 'orderby', string $orderDirectionKey = 'order'): array
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
        return $item[$column_name];
    }

    /**
     * Generates a checkbox for a row to add row to bulk actions
     *
     * @param array $item
     *
     * @return string
     */
    public function column_cb($item): string
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

    public function get_columns(): array
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
            'locales'      => __('Target blogs'),
            'updated'      => __('Updated'),
        ];
    }

    public function get_sortable_columns(): array
    {
        $sortable_columns = [];
        if ($this->getSubmissionStatusFilterValue() !== null) {
            return $sortable_columns;
        }

        foreach (['title', 'author', 'updated'] as $field) {
            $sortable_columns[$field] = [$field, false];
        }

        return $sortable_columns;
    }

    public function processBulkAction(): void
    {
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

            $queueIds = new IntegerIterator();
            if (is_array($submissions) && count($locales) > 0) {
                $clone = 'clone' === $action;
                foreach ($submissions as $submission) {
                    [$id] = explode('-', $submission);
                    $type = $this->getContentTypeFilterValue();
                    $curBlogId = $this->getProfile()->getOriginalBlogId()->getBlogId();
                    foreach ($locales as $blogId => $blogName) {
                        $submissionId =  $this->core->prepareForUpload(
                            $type,
                            $curBlogId,
                            $id,
                            (int)$blogId,
                            new JobEntityWithBatchUid($batchUid, $jobName, $clone ? '' : $smartlingData['jobId'], $profile->getProjectId()),
                            $clone,
                        )->getId();
                        if (!$clone) {
                            $queueIds[] = $submissionId;
                        }
                    }

                }
                $this->uploadQueueManager->enqueue($queueIds, $batchUid);
            }
        }
    }

    private function getContentTypeFilterValue(): ?string
    {
        $value = $this->getFormElementValue(
            self::CONTENT_TYPE_SELECT_ELEMENT_NAME,
            $this->defaultValues[self::CONTENT_TYPE_SELECT_ELEMENT_NAME]
        );

        return 'any' === $value ? null : $value;
    }

    private function getSubmissionStatusFilterValue(): ?string
    {
        $value = $this->getFormElementValue(self::SUBMISSION_STATUS_SELECT_ELEMENT_NAME, $this->defaultValues[self::SUBMISSION_STATUS_SELECT_ELEMENT_NAME]);

        return $this->defaultValues[self::SUBMISSION_STATUS_SELECT_ELEMENT_NAME] === $value ? null : $value;
    }

    private function getTitleSearchTextFilterValue(): ?string
    {
        return $this->getFormElementValue(
            self::TITLE_SEARCH_TEXTBOX_ELEMENT_NAME,
            $this->defaultValues[self::TITLE_SEARCH_TEXTBOX_ELEMENT_NAME]
        );
    }

    public function prepare_items(): void
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
        $this->processBulkAction();

        $contentTypeFilterValue = $this->getContentTypeFilterValue();
        $sortOptions = $this->getSortingOptions();
        $submissionStatusFilterValue = $this->getSubmissionStatusFilterValue();

        $io = $this->core->getContentIoFactory()->getMapper($contentTypeFilterValue);
        $searchString = $this->getTitleSearchTextFilterValue();
        $ids = [];
        if ($submissionStatusFilterValue !== null) {
            $parameters = [
                SubmissionEntity::FIELD_CONTENT_TYPE => $contentTypeFilterValue,
                SubmissionEntity::FIELD_SOURCE_BLOG_ID => $this->siteHelper->getCurrentBlogId(),
                SubmissionEntity::FIELD_STATUS => $submissionStatusFilterValue,
            ];
            $submissions = $this->manager->find($parameters, $pageOptions['limit'], $pageOptions['page']);
            foreach ($submissions as $submission) {
                $ids[] = $submission->getSourceId();
            }
            $data = count($submissions) > 0 ? $io->getAll(ids: $ids) : [];
            $total = $this->manager->count($this->manager->buildConditionBlockFromSearchParameters($parameters));
        } else {
            $data = $io->getAll(
                $pageOptions['limit'],
                ($pageOptions['page'] - 1) * $pageOptions['limit'],
                $sortOptions['orderby'],
                $sortOptions['order'],
                $searchString
            );
            $total = $io->getTotal();
        }

        $dataAsArray = [];
        foreach ($data as $item) {
            $row = $item->toBulkSubmitScreenRow()->toArray();
            $entities = $this->manager->find([
                SubmissionEntity::FIELD_SOURCE_BLOG_ID => $this->siteHelper->getCurrentBlogId(),
                SubmissionEntity::FIELD_SOURCE_ID => $row['id'],
                SubmissionEntity::FIELD_CONTENT_TYPE => $this->getContentTypeFilterValue(),
            ]);

            if (count($entities) > 0) {
                $locales = [];
                foreach ($entities as $entity) {
                    try {
                        $blogLabel = $this->siteHelper
                            ->getBlogLabelById($this->localizationPluginProxy, $entity->getTargetBlogId());
                    } catch (BlogNotFoundException) {
                        $blogLabel = "UNKNOWN (blogId={$entity->getTargetBlogId()})";
                    }
                    $locales[] = "<div style=\"display: inline-block\">$blogLabel
    <span class=\"widget-btn {$entity->getStatusColor()}\" style=\"margin-left: 3px; position: static\" title=\"{$entity->getStatus()} {$entity->getCompletionPercentage()}%\"/>
</div>";
                }

                $row['locales'] = implode(', ', $locales);
            }

            $file_uri_max_chars = 50;
            if (mb_strlen($row['title'], 'utf8') > $file_uri_max_chars) {
                $orig = $row['title'];
                $row['title'] = HtmlTagGeneratorHelper::tag('span', mb_substr($orig, 0, $file_uri_max_chars - 3, 'utf8') . '...', ['title' => $orig]);
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

        $foundCount = count($dataAsArray);
        $dataAsArray = apply_filters(ExportedAPI::FILTER_BULK_SUBMIT_PREPARE_ITEMS, $dataAsArray);
        if (count($dataAsArray) !== $foundCount) {
            $this->dataFiltered = true;
        }
        $this->items = $dataAsArray;

        $this->set_pagination_args([
                                       'total_items' => $total,
                                       'per_page'    => $pageOptions['limit'],
                                       'total_pages' => ceil($total / $pageOptions['limit']),
                                   ]);
    }

    private function getFilteredAllowedTypes(): array
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

    public function contentTypeSelectRender(): string
    {
        $controlName = self::CONTENT_TYPE_SELECT_ELEMENT_NAME;
        $typesFiltered = $this->getFilteredAllowedTypes();

        $value = $this->getFormElementValue(
            $controlName,
            $this->defaultValues[$controlName]
        );

        return HtmlTagGeneratorHelper::tag(
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
    }

    public function submissionsStatusFilterRender(): string
    {
        return HtmlTagGeneratorHelper::tag('label', 'Submission Status', ['for' => $this->buildHtmlTagName(self::SUBMISSION_STATUS_SELECT_ELEMENT_NAME)]) .
            HtmlTagGeneratorHelper::tag(
                'select',
                HtmlTagGeneratorHelper::renderSelectOptions(
                    $this->getFormElementValue(
                        self::SUBMISSION_STATUS_SELECT_ELEMENT_NAME,
                        $this->defaultValues[self::SUBMISSION_STATUS_SELECT_ELEMENT_NAME]
                    ),
                    array_merge(
                        [$this->defaultValues[self::SUBMISSION_STATUS_SELECT_ELEMENT_NAME] => $this->defaultValues[self::SUBMISSION_STATUS_SELECT_ELEMENT_NAME]], SubmissionEntity::getSubmissionStatusLabels(),
                    ),
                ),
                [
                    'id' => $this->buildHtmlTagName(self::SUBMISSION_STATUS_SELECT_ELEMENT_NAME),
                    'name' => $this->buildHtmlTagName(self::SUBMISSION_STATUS_SELECT_ELEMENT_NAME),
                ]
            );
    }

    public function titleFilterRender(): string
    {
        $controlName = self::TITLE_SEARCH_TEXTBOX_ELEMENT_NAME;

        $value = $this->getFormElementValue(
            $controlName,
            $this->defaultValues[$controlName]
        );

        return HtmlTagGeneratorHelper::tag(
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
    }

    public function renderSubmitButton(string $label): string
    {
        $id = $this->buildHtmlTagName('go-and-filter');

        $options = [
            'type'  => 'submit',
            'id'    => $id,
            'class' => 'button action',
            'value' => __($label),

        ];

        return HtmlTagGeneratorHelper::tag('input', '', $options);
    }

    /**
     * Retrieves from source array value for input element
     */
    private function getFormElementValue(string $name, mixed $defaultValue): mixed
    {
        return $this->getFromSource($this->buildHtmlTagName($name), $defaultValue);
    }

    /**
     * Builds namespaced attribute value for HTML Form element tag
     */
    private function buildHtmlTagName(string $name): string
    {
        return self::CUSTOM_CONTROLS_NAMESPACE . '-' . $name;
    }
}
