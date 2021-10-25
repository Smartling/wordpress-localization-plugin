<?php

namespace Smartling\WP\Table;

use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\Exception\BlogNotFoundException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\CommonLogMessagesTrait;
use Smartling\Helpers\DateTimeHelper;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\EntityHelper;
use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Helpers\QueryBuilder\Condition\Condition;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBlock;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBuilder;
use Smartling\Helpers\StringHelper;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Jobs\JobEntity;
use Smartling\Jobs\JobManager;
use Smartling\Queue\Queue;
use Smartling\Settings\Locale;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\Vendor\Psr\Log\LoggerInterface;
use Smartling\WP\Controller\SmartlingListTable;

class SubmissionTableWidget extends SmartlingListTable
{
    use CommonLogMessagesTrait;

    private const ACTION_DOWNLOAD = 'download';
    private const ACTION_LOCK = 'lock';
    private const ACTION_UNLOCK = 'unlock';

    /**
     * base name of Content-type filtering select
     */
    private const CONTENT_TYPE_SELECT_ELEMENT_NAME = 'content-type';

    /**
     * base name of status filtering select
     */
    private const SUBMISSION_STATUS_SELECT_ELEMENT_NAME = 'status';

    private const SUBMISSION_OUTDATED_STATE = 'state_outdated';
    private const SUBMISSION_LOCKED_STATE = 'state_locked';
    private const SUBMISSION_CLONED_STATE = 'state_cloned';
    private const SUBMISSION_TARGET_LOCALE = 'target-locale';

    private LoggerInterface $logger;
    private SubmissionManager $submissionManager;
    private EntityHelper $entityHelper;
    private Queue $queue;
    private JobManager $jobInformationManager;

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    private string $_custom_controls_namespace = 'smartling-submissions-page';

    /**
     * default values of custom form elements on page
     */
    private array $defaultValues = [
        self::CONTENT_TYPE_SELECT_ELEMENT_NAME => 'any',
        self::SUBMISSION_STATUS_SELECT_ELEMENT_NAME => null,
        self::SUBMISSION_OUTDATED_STATE => 'any',
        self::SUBMISSION_TARGET_LOCALE => 'any',
        self::SUBMISSION_CLONED_STATE => 'any',
        self::SUBMISSION_LOCKED_STATE => 'any',
    ];

    private array $_settings = ['singular' => 'submission', 'plural' => 'submissions', 'ajax' => false,];

    public function __construct(SubmissionManager $manager, EntityHelper $entityHelper, Queue $queue, JobManager $jobInformationManager)
    {
        $this->queue = $queue;
        $this->submissionManager = $manager;
        $this->setSource($_REQUEST);
        $this->entityHelper = $entityHelper;

        $this->defaultValues[self::SUBMISSION_STATUS_SELECT_ELEMENT_NAME] = $manager->getDefaultSubmissionStatus();

        $this->logger = $entityHelper->getLogger();

        parent::__construct($this->_settings);
        $this->jobInformationManager = $jobInformationManager;
    }

    public function getSortingOptions(string $fieldNameKey = 'orderby', string $orderDirectionKey = 'order'): array
    {
        $options = [];

        $column = $this->getFromSource($fieldNameKey, false);

        if (false !== $column) {
            $direction = strtoupper($this->getFromSource($orderDirectionKey, SmartlingToCMSDatabaseAccessWrapperInterface::SORT_OPTION_ASC));

            $options = [$column => $direction];
        }

        return $options;
    }

    /**
     * @param object $item
     * @param string $column_name
     * @return mixed
     */
    public function column_default($item, $column_name)
    {
        return $item[$column_name];
    }

    /**
     * Generates a checkbox for a row to add row to bulk actions
     *
     * @param array $item
     */
    public function column_cb($item): string
    {
        return HtmlTagGeneratorHelper::tag(
            'input',
            '',
            [
                'type'  => 'checkbox',
                'name'  => $this->buildHtmlTagName($this->_args['singular']) . '[]',
                'value' => $item['id'],
                'id'    => 'submission-id-' . $item['id'],
                'class' => 'bulkaction',
            ]
        );
    }

    public function get_columns(): array
    {
        $columns = $this->submissionManager->getColumnsLabels();
        $columns['outdated'] = 'States';

        return array_merge(['bulkActionCb' => '<input type="checkbox" class="checkall" />'], $columns);
    }

    public function get_sortable_columns(): array
    {

        $fields = $this->submissionManager->getSortableFields();

        $sortable_columns = [];

        foreach ($fields as $field) {
            $sortable_columns[$field] = [$field, false];
        }

        return $sortable_columns;
    }

    public function get_bulk_actions(): array
    {
        return [
            self::ACTION_DOWNLOAD => __('Enqueue for Download'),
            self::ACTION_LOCK     => __('Lock translation'),
            self::ACTION_UNLOCK   => __('Unlock translation'),
        ];
    }

    /**
     * Handles actions for multiple objects
     */
    private function processBulkAction(): void
    {
        /**
         * @var int[] $submissionsIds
         */
        $submissionsIds = $this->getFormElementValue('submission', []);

        if (is_array($submissionsIds) && 0 < count($submissionsIds)) {
            $submissions = $this->submissionManager->findByIds($submissionsIds);
            if (0 < count($submissions)) {
                switch ($this->current_action()) {
                    case self::ACTION_DOWNLOAD:
                        foreach ($submissions as $submission) {
                            $this->queue->enqueue([$submission->getId()], Queue::QUEUE_NAME_DOWNLOAD_QUEUE);
                        }
                        break;
                    case self::ACTION_LOCK:
                        foreach ($submissions as $submission) {
                            $submission->setIsLocked(1);
                            $this->submissionManager->storeEntity($submission);
                        }
                        break;
                    case self::ACTION_UNLOCK:
                        foreach ($submissions as $submission) {
                            $submission->setIsLocked(0);
                            $this->submissionManager->storeEntity($submission);
                        }
                        break;
                    default:
                        break;
                }
            }
        }
    }

    /**
     * Handles actions
     */
    private function processAction(): void
    {
        try {
            $this->processBulkAction();
        } catch (\Exception $e) {
            DiagnosticsHelper::addDiagnosticsMessage($e->getMessage());
        }
    }

    private function getContentTypeFilterValue(): ?string
    {
        $value = $this->getFormElementValue(self::CONTENT_TYPE_SELECT_ELEMENT_NAME, $this->defaultValues[self::CONTENT_TYPE_SELECT_ELEMENT_NAME]);

        return 'any' === $value ? null : $value;
    }

    private function getStatusFilterValue(): ?string
    {
        $value = $this->getFormElementValue(self::SUBMISSION_STATUS_SELECT_ELEMENT_NAME, $this->defaultValues[self::SUBMISSION_STATUS_SELECT_ELEMENT_NAME]);

        return 'any' === $value ? null : $value;
    }

    private function getOutdatedFlagFilterValue(): ?int
    {
        $value = $this->getFormElementValue(self::SUBMISSION_OUTDATED_STATE, $this->defaultValues[self::SUBMISSION_OUTDATED_STATE]);

        return 'any' === $value ? null : (int)$value;
    }

    private function getLockedFlagFilterValue(): ?string
    {
        return $this->getFormElementValue(self::SUBMISSION_LOCKED_STATE, $this->defaultValues[self::SUBMISSION_LOCKED_STATE]);
    }

    private function getClonedFlagFilterValue(): ?string
    {
        return $this->getFormElementValue(self::SUBMISSION_CLONED_STATE, $this->defaultValues[self::SUBMISSION_CLONED_STATE]);
    }

    private function getTargetLocaleFilterValue(): ?int
    {
        $value = $this->getFormElementValue(self::SUBMISSION_TARGET_LOCALE, $this->defaultValues[self::SUBMISSION_TARGET_LOCALE]);

        return 'any' === $value ? null : (int)$value;
    }

    public function prepare_items(): void
    {
        $siteHelper = $this->entityHelper->getSiteHelper();
        $pageOptions = ['limit' => $this->submissionManager->getPageSize(), 'page' => $this->get_pagenum(),];

        $this->_column_headers = [$this->get_columns(), ['id'], $this->get_sortable_columns(),];

        $this->processAction();

        $total = 0;

        $contentTypeFilterValue = $this->getContentTypeFilterValue();
        $statusFilterValue = $this->getStatusFilterValue();
        $outdatedFlag = $this->getOutdatedFlagFilterValue();
        $lockedFlag = $this->getLockedFlagFilterValue();
        $clonedFlag = $this->getClonedFlagFilterValue();
        $targetLocale = $this->getTargetLocaleFilterValue();
        $searchText = $this->getFromSource('s', '');

        $block = ConditionBlock::getConditionBlock();

        if (!StringHelper::isNullOrEmpty(trim($searchText))) {

            $searchText = vsprintf('%%%s%%', [trim($searchText)]);

            $searchBlock = ConditionBlock::getConditionBlock(ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_OR);

            $searchFields = [
                JobEntity::FIELD_JOB_NAME,
                SubmissionEntity::FIELD_SOURCE_TITLE,
                SubmissionEntity::FIELD_FILE_URI,
            ];

            foreach ($searchFields as $searchField) {
                $searchBlock->addCondition(
                    Condition::getCondition(
                        ConditionBuilder::CONDITION_SIGN_LIKE,
                        $searchField,
                        [$searchText]
                    )
                );
            }

            $block->addConditionBlock($searchBlock);
        }

        if (null !== $targetLocale) {
            $block->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, SubmissionEntity::FIELD_TARGET_BLOG_ID, [$targetLocale]));
        }

        if ('any' !== $lockedFlag) {
            $lockConditionTotal = Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, SubmissionEntity::FIELD_IS_LOCKED, [(int)$lockedFlag]);

            if (1 === (int)$lockedFlag) {
                $lockConditionFields = Condition::getCondition(ConditionBuilder::CONDITION_IS_NOT_NULL, SubmissionEntity::FIELD_LOCKED_FIELDS, []);
                $lockConditionFields2 = Condition::getCondition(ConditionBuilder::CONDITION_SIGN_NOT_EQ, SubmissionEntity::FIELD_LOCKED_FIELDS, [serialize([])]);
            } else {
                $lockConditionFields = Condition::getCondition(ConditionBuilder::CONDITION_IS_NULL, SubmissionEntity::FIELD_LOCKED_FIELDS, []);
                $lockConditionFields2 = Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, SubmissionEntity::FIELD_LOCKED_FIELDS, [serialize([])]);
            }

            $lockBlockHL = ConditionBlock::getConditionBlock();

            $lockBlock = ConditionBlock::getConditionBlock(ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_OR);
            $lockBlockHL->addCondition($lockConditionTotal);


            $lockBlock->addCondition($lockConditionFields);
            $lockBlock->addCondition($lockConditionFields2);

            $lockBlockHL->addConditionBlock($lockBlock);
            $block->addConditionBlock($lockBlockHL);
        }

        if ('any' !== $clonedFlag) {
            $block->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, SubmissionEntity::FIELD_IS_CLONED, [(int)$clonedFlag]));
        }

        $data = $this->submissionManager->searchByCondition(
            $block,
            $contentTypeFilterValue,
            $statusFilterValue,
            $outdatedFlag,
            $this->getSortingOptions(),
            $pageOptions,
            $total
        );

        $dataAsArray = [];

        $file_uri_max_chars = 50;

        foreach ($data as $element) {
            $row = $element->toArray();
            $jobInfo = $element->getJobInfo();

            $fileName = htmlentities($row[SubmissionEntity::FIELD_FILE_URI]);
            $row[SubmissionEntity::FIELD_FILE_URI] = $fileName;
            $row[SubmissionEntity::FIELD_SOURCE_TITLE] = htmlentities($row[SubmissionEntity::FIELD_SOURCE_TITLE]);
            $row[SubmissionEntity::FIELD_CONTENT_TYPE] = WordpressContentTypeHelper::getLocalizedContentType($row[SubmissionEntity::FIELD_CONTENT_TYPE]);
            $row[SubmissionEntity::FIELD_SUBMISSION_DATE] = DateTimeHelper::toWordpressLocalDateTime(DateTimeHelper::stringToDateTime($row[SubmissionEntity::FIELD_SUBMISSION_DATE]));
            $row[SubmissionEntity::FIELD_APPLIED_DATE] = '0000-00-00 00:00:00' === $row[SubmissionEntity::FIELD_APPLIED_DATE] ? __('Never')
                : DateTimeHelper::toWordpressLocalDateTime(DateTimeHelper::stringToDateTime($row[SubmissionEntity::FIELD_APPLIED_DATE]));
            try {
                $blogLabel = $siteHelper->getBlogLabelById($this->entityHelper->getConnector(), $row[SubmissionEntity::FIELD_TARGET_BLOG_ID]);
            } catch (BlogNotFoundException $e) {
                $blogLabel = "*blog id {$row[SubmissionEntity::FIELD_TARGET_BLOG_ID]} not found*";
            }
            $row[SubmissionEntity::FIELD_TARGET_LOCALE] = $blogLabel;
            $row[SubmissionEntity::VIRTUAL_FIELD_JOB_LINK] = $jobInfo->getJobName() === '' ? '' : "<a href=\"https://dashboard.smartling.com/app/projects/{$jobInfo->getProjectUid()}/account-jobs/?filename=$fileName\">{$jobInfo->getJobName()}</a>";

            $flagBlockParts = [];

            foreach ($element->getStatusFlags() as $k => $v) {
                $flagBlockParts[] = HtmlTagGeneratorHelper::tag(
                    'span',
                    '',
                    [
                        'class' => vsprintf('status-flag-%s %s', [$k, $v]),
                        'title' => ucfirst($k),
                    ]
                );
            }

            $row['outdated'] = implode('', $flagBlockParts);

            if (SubmissionEntity::SUBMISSION_STATUS_FAILED === $row['status'] &&
                !StringHelper::isNullOrEmpty($row[SubmissionEntity::FIELD_LAST_ERROR])
            ) {
                $row[SubmissionEntity::FIELD_STATUS] = HtmlTagGeneratorHelper::tag('span', $row[SubmissionEntity::FIELD_STATUS], [
                    'class' => 'submission-failed',
                    'title' => trim($row[SubmissionEntity::FIELD_LAST_ERROR]),
                ]);
            }

            if (mb_strlen($row[SubmissionEntity::FIELD_FILE_URI], 'utf8') > $file_uri_max_chars) {
                $orig = $row[SubmissionEntity::FIELD_FILE_URI];
                $shrinked = mb_substr($orig, 0, $file_uri_max_chars - 3, 'utf8') . '...';

                $row[SubmissionEntity::FIELD_FILE_URI] = HtmlTagGeneratorHelper::tag('span', $shrinked, ['title' => $orig]);
            }

            $row['bulkActionCb'] = $this->column_cb($row);

            $dataAsArray[] = $row;
        }


        $this->items = $dataAsArray;

        $this->set_pagination_args(['total_items' => $total, 'per_page' => $pageOptions['limit'],
                                    'total_pages' => ceil($total / $pageOptions['limit']),]);
    }

    public function statusSelectRender(): string
    {
        $controlName = 'status';

        $statuses = $this->submissionManager->getSubmissionStatusLabels();

        // add 'Any' to turn off filter
        $statuses = array_merge(['any' => __('Any')], $statuses);

        $value = $this->getFormElementValue($controlName, $this->defaultValues[$controlName]);

        return HtmlTagGeneratorHelper::tag('label', __('Status'), ['for' => $this->buildHtmlTagName($controlName),]) .
            HtmlTagGeneratorHelper::tag(
                'select',
                HtmlTagGeneratorHelper::renderSelectOptions($value, $statuses), [
                    'id' => $this->buildHtmlTagName($controlName),
                    'name' => $this->buildHtmlTagName($controlName),
                ]
            );
    }

    public function outdatedStateSelectRender(): string
    {
        $controlName = self::SUBMISSION_OUTDATED_STATE;

        $states = [
            0 => __('Up to Date'),
            1 => __('Outdated'),
        ];

        // add 'Any' to turn off filter
        $states = array_merge(['any' => __('Any')], $states);

        $value = $this->getFormElementValue($controlName, $this->defaultValues[$controlName]);

        return HtmlTagGeneratorHelper::tag('label', __('Content Status'), ['for' => $this->buildHtmlTagName($controlName),]) .
            HtmlTagGeneratorHelper::tag(
                'select',
                HtmlTagGeneratorHelper::renderSelectOptions($value, $states), [
                'id' => $this->buildHtmlTagName($controlName),
                'name' => $this->buildHtmlTagName($controlName),
                ]
            );
    }

    public function lockedStateSelectRender(): string
    {
        $controlName = self::SUBMISSION_LOCKED_STATE;

        $states = [
            0 => __('Not locked'),
            1 => __('Locked'),
        ];

        // add 'Any' to turn off filter
        $states = array_merge(['any' => __('Any')], $states);

        $value = $this->getFormElementValue($controlName, $this->defaultValues[$controlName]);

        return HtmlTagGeneratorHelper::tag('label', __('Lock Status'), ['for' => $this->buildHtmlTagName($controlName),]) .
            HtmlTagGeneratorHelper::tag(
                'select',
                HtmlTagGeneratorHelper::renderSelectOptions($value, $states), [
                    'id' => $this->buildHtmlTagName($controlName),
                    'name' => $this->buildHtmlTagName($controlName),
                ]
            );
    }

    public function clonedStateSelectRender(): string
    {
        $controlName = self::SUBMISSION_CLONED_STATE;

        $states = [
            0 => __('Translated'),
            1 => __('Cloned'),
        ];

        // add 'Any' to turn off filter
        $states = array_merge(['any' => __('Any')], $states);

        $value = $this->getFormElementValue($controlName, $this->defaultValues[$controlName]);

        return HtmlTagGeneratorHelper::tag('label', __('Clone Status'), ['for' => $this->buildHtmlTagName($controlName),]) .
            HtmlTagGeneratorHelper::tag(
                'select',
                HtmlTagGeneratorHelper::renderSelectOptions($value, $states), [
                    'id' => $this->buildHtmlTagName($controlName),
                    'name' => $this->buildHtmlTagName($controlName),
                ]
            );
    }

    public function renderSearchBox(): string
    {
        return HtmlTagGeneratorHelper::tag('label', __('Search'), ['for' => 's'])
                . HtmlTagGeneratorHelper::tag(
                'input',
                '',
                [
                    'name'        => 's',
                    'type'        => 'text',
                    'value'       => $this->getFormElementValue('s', ''),
                    'placeholder' => __('Search text'),
                ]
            );
    }

    public function targetLocaleSelectRender(): string
    {
        $controlName = self::SUBMISSION_TARGET_LOCALE;

        $siteHelper = $this->entityHelper->getSiteHelper();
        $locales = [];
        foreach ($siteHelper->listBlogs() as $blogId) {
            try {
                $locale = new Locale();
                $locale->setBlogId($blogId);
                $locale->setLabel($siteHelper->getBlogLabelById($this->entityHelper->getConnector(), $blogId));
                $locales[] = $locale;
            } catch (BlogNotFoundException $e) {
                $this->getLogger()->warning($e->getMessage());
            }
        }

        ArrayHelper::sortLocales($locales);

        $_locales = [
            'any' => __('Any'),
        ];

        foreach ($locales as $locale) {
            $_locales[$locale->getBlogId()] = $locale->getLabel();
        }

        $value = $this->getFormElementValue($controlName, $this->defaultValues[$controlName]);
        return HtmlTagGeneratorHelper::tag(
                'label',
                __('Target Site'),
                ['for' => $this->buildHtmlTagName($controlName)]
            ) . HtmlTagGeneratorHelper::tag(
                'select',
                HtmlTagGeneratorHelper::renderSelectOptions($value, $_locales),
                ['id' => $this->buildHtmlTagName($controlName), 'name' => $this->buildHtmlTagName($controlName)]
            );
    }


    public function contentTypeSelectRender(): string
    {
        $controlName = 'content-type';

        $types = $this->getActiveContentTypes($this->entityHelper->getSiteHelper(), 'submissionBoard');

        // add 'Any' to turn off filter
        $types = array_merge(['any' => __('Any')], $types);

        $value = $this->getFormElementValue($controlName, $this->defaultValues[$controlName]);

        return HtmlTagGeneratorHelper::tag('label', __('Type'), ['for' => $this->buildHtmlTagName($controlName),]) .
            HtmlTagGeneratorHelper::tag(
                'select',
                HtmlTagGeneratorHelper::renderSelectOptions($value, $types), [
                    'id' => $this->buildHtmlTagName($controlName),
                    'name' => $this->buildHtmlTagName($controlName),
                ]
            );
    }

    public function renderSubmitButton(string $label): string
    {
        $id = $this->buildHtmlTagName('go-and-filter');

        $options = ['id' => $id, 'name' => '', 'class' => 'button action',
        ];

        return HtmlTagGeneratorHelper::submitButton($label, $options);
    }

    /**
     * Retrieves from source array value for input element
     *
     * @param string $name
     * @param mixed  $defaultValue
     *
     * @return mixed
     */
    private function getFormElementValue(string $name, $defaultValue)
    {
        return $this->getFromSource($this->buildHtmlTagName($name), $defaultValue);
    }


    /**
     * Builds unique name attribute value for HTML Form element tag
     */
    private function buildHtmlTagName(string $name): string
    {
        return $name;
    }
}
