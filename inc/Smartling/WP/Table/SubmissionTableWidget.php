<?php

namespace Smartling\WP\Table;

use Psr\Log\LoggerInterface;
use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
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
use Smartling\Queue\Queue;
use Smartling\Settings\Locale;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\WP\Controller\SmartlingListTable;

/**
 * Class SubmissionTableWidget
 * @package Smartling\WP\Table
 */
class SubmissionTableWidget extends SmartlingListTable
{

    use CommonLogMessagesTrait;

    const ACTION_UPLOAD   = 'send';
    const ACTION_DOWNLOAD = 'download';
    const ACTION_LOCK     = 'lock';
    const ACTION_UNLOCK   = 'unlock';

    /**
     * base name of Content-type filtering select
     */
    const CONTENT_TYPE_SELECT_ELEMENT_NAME = 'content-type';

    /**
     * base name of status filtering select
     */
    const SUBMISSION_STATUS_SELECT_ELEMENT_NAME = 'status';


    const SUBMISSION_OUTDATE_STATE = 'state_outdated';

    const SUBMISSION_LOCKED_STATE = 'state_locked';

    const SUBMISSION_CLONED_STATE = 'state_cloned';


    const SUBMISSION_TARGET_LOCALE = 'target-locale';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var SubmissionManager $manager
     */
    private $manager;

    /**
     * @var EntityHelper
     */
    private $entityHelper;

    /**
     * @var Queue
     */
    private $queue;

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param LoggerInterface $logger
     */
    private function setLogger($logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return Queue
     */
    public function getQueue()
    {
        return $this->queue;
    }

    /**
     * @param mixed $queue
     */
    public function setQueue($queue)
    {
        $this->queue = $queue;
    }

    /**
     * @var string
     */
    private $_custom_controls_namespace = 'smartling-submissions-page';

    /**
     * default values of custom form elements on page
     * @var array
     */
    private $defaultValues = [
        self::CONTENT_TYPE_SELECT_ELEMENT_NAME      => 'any',
        self::SUBMISSION_STATUS_SELECT_ELEMENT_NAME => null,
        self::SUBMISSION_OUTDATE_STATE              => 'any',
        self::SUBMISSION_TARGET_LOCALE              => 'any',
        self::SUBMISSION_CLONED_STATE               => 'any',
        self::SUBMISSION_LOCKED_STATE               => 'any',
    ];

    private $_settings = ['singular' => 'submission', 'plural' => 'submissions', 'ajax' => false,];

    /**
     * @param SubmissionManager $manager
     * @param EntityHelper      $entityHelper
     * @param Queue             $queue
     */
    public function __construct(SubmissionManager $manager, EntityHelper $entityHelper, Queue $queue)
    {
        $this->setQueue($queue);
        $this->manager = $manager;
        $this->setSource($_REQUEST);
        $this->entityHelper = $entityHelper;

        $this->defaultValues[self::SUBMISSION_STATUS_SELECT_ELEMENT_NAME] = $manager->getDefaultSubmissionStatus();

        $this->setLogger($entityHelper->getLogger());

        parent::__construct($this->_settings);
    }

    /**
     * @param string $fieldNameKey
     * @param string $orderDirectionKey
     *
     * @return array
     */
    public function getSortingOptions($fieldNameKey = 'orderby', $orderDirectionKey = 'order')
    {
        $options = [];

        $column = $this->getFromSource($fieldNameKey, false);

        if (false !== $column) {
            $direction = strtoupper($this->getFromSource($orderDirectionKey, SmartlingToCMSDatabaseAccessWrapperInterface::SORT_OPTION_ASC));

            $options = [$column => $direction];
        }

        return $options;
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

    /**
     * @inheritdoc
     */
    public function get_columns()
    {
        $columns = $this->manager->getColumnsLabels();

        $columns['outdated'] = 'States';

        $columns = array_merge(['bulkActionCb' => '<input type="checkbox" class="checkall" />'], $columns);

        return $columns;
    }

    /**
     * @inheritdoc
     */
    public function get_sortable_columns()
    {

        $fields = $this->manager->getSortableFields();

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
        $actions = [
            self::ACTION_DOWNLOAD => __('Enqueue for Download'),
            self::ACTION_LOCK     => __('Lock translation'),
            self::ACTION_UNLOCK   => __('Unlock translation'),
        ];

        return $actions;
    }

    /**
     * Handles actions for multiply objects
     */
    private function processBulkAction()
    {
        /**
         * @var int[] $submissionsIds
         */
        $submissionsIds = $this->getFormElementValue('submission', []);

        if (is_array($submissionsIds) && 0 < count($submissionsIds)) {
            $submissions = $this->manager->findByIds($submissionsIds);
            if (0 < count($submissions)) {
                switch ($this->current_action()) {
                    case self::ACTION_DOWNLOAD:
                        foreach ($submissions as $submission) {
                            $this->getQueue()->enqueue([$submission->getId()], Queue::QUEUE_NAME_DOWNLOAD_QUEUE);
                        }
                        break;
                    case self::ACTION_LOCK:
                        foreach ($submissions as $submission) {
                            $submission->setIsLocked(1);
                            $this->manager->storeEntity($submission);
                        }
                        break;
                    case self::ACTION_UNLOCK:
                        foreach ($submissions as $submission) {
                            $submission->setIsLocked(0);
                            $this->manager->storeEntity($submission);
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
    private function processAction()
    {
        try {
            $this->processBulkAction();
        } catch (EntityNotFoundException $e) {
            $msg = 'An error occurred, the database is corrupted. ' . $e->getMessage();
            DiagnosticsHelper::addDiagnosticsMessage($msg);
        } catch (Exception $e) {
            DiagnosticsHelper::addDiagnosticsMessage($e->getMessage());
        }
    }

    /**
     * @return string|null
     */
    private function getContentTypeFilterValue()
    {
        $value = $this->getFormElementValue(self::CONTENT_TYPE_SELECT_ELEMENT_NAME, $this->defaultValues[self::CONTENT_TYPE_SELECT_ELEMENT_NAME]);

        return 'any' === $value ? null : $value;
    }

    /**
     * @return string|null
     */
    private function getStatusFilterValue()
    {
        $value = $this->getFormElementValue(self::SUBMISSION_STATUS_SELECT_ELEMENT_NAME, $this->defaultValues[self::SUBMISSION_STATUS_SELECT_ELEMENT_NAME]);

        return 'any' === $value ? null : $value;
    }

    /**
     * @return int|null
     */
    private function getOutdatedFlagFilterValue()
    {
        $value = $this->getFormElementValue(self::SUBMISSION_OUTDATE_STATE, $this->defaultValues[self::SUBMISSION_OUTDATE_STATE]);

        return 'any' === $value ? null : (int)$value;
    }

    /**
     * @return int|null
     */
    private function getLockedFlagFilterValue()
    {
        return $this->getFormElementValue(self::SUBMISSION_LOCKED_STATE, $this->defaultValues[self::SUBMISSION_LOCKED_STATE]);
    }

    /**
     * @return int|null
     */
    private function getClonedFlagFilterValue()
    {
        return $this->getFormElementValue(self::SUBMISSION_CLONED_STATE, $this->defaultValues[self::SUBMISSION_CLONED_STATE]);
    }

    /**
     * @return int|null
     */
    private function getTargetLocaleFilterValue()
    {
        $value = $this->getFormElementValue(self::SUBMISSION_TARGET_LOCALE, $this->defaultValues[self::SUBMISSION_TARGET_LOCALE]);

        return 'any' === $value ? null : (int)$value;
    }

    /**
     * @inheritdoc
     */
    public function prepare_items()
    {
        $siteHelper = $this->entityHelper->getSiteHelper();
        $pageOptions = ['limit' => $this->manager->getPageSize(), 'page' => $this->get_pagenum(),];

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
                SubmissionEntity::FIELD_SOURCE_TITLE,
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

        $data = $this->manager->searchByCondition(
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

            $row[SubmissionEntity::FIELD_FILE_URI] = htmlentities($row[SubmissionEntity::FIELD_FILE_URI]);
            $row[SubmissionEntity::FIELD_SOURCE_TITLE] = htmlentities($row[SubmissionEntity::FIELD_SOURCE_TITLE]);
            $row[SubmissionEntity::FIELD_CONTENT_TYPE] = WordpressContentTypeHelper::getLocalizedContentType($row[SubmissionEntity::FIELD_CONTENT_TYPE]);
            $row[SubmissionEntity::FIELD_SUBMISSION_DATE] = DateTimeHelper::toWordpressLocalDateTime(DateTimeHelper::stringToDateTime($row[SubmissionEntity::FIELD_SUBMISSION_DATE]));
            $row[SubmissionEntity::FIELD_APPLIED_DATE] = '0000-00-00 00:00:00' === $row[SubmissionEntity::FIELD_APPLIED_DATE] ? __('Never')
                : DateTimeHelper::toWordpressLocalDateTime(DateTimeHelper::stringToDateTime($row[SubmissionEntity::FIELD_APPLIED_DATE]));
            $row[SubmissionEntity::FIELD_TARGET_LOCALE] = $siteHelper->getBlogLabelById($this->entityHelper->getConnector(), $row[SubmissionEntity::FIELD_TARGET_BLOG_ID]);

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


            $row = array_merge(['bulkActionCb' => $this->column_cb($row)], $row);

            $dataAsArray[] = $row;
        }


        $this->items = $dataAsArray;

        $this->set_pagination_args(['total_items' => $total, 'per_page' => $pageOptions['limit'],
                                    'total_pages' => ceil($total / $pageOptions['limit']),]);
    }

    /**
     * @return string
     */
    public function statusSelectRender()
    {
        $controlName = 'status';

        $statuses = $this->manager->getSubmissionStatusLabels();

        // add 'Any' to turn off filter
        $statuses = array_merge(['any' => __('Any')], $statuses);

        $value = $this->getFormElementValue($controlName, $this->defaultValues[$controlName]);

        $html = HtmlTagGeneratorHelper::tag('label', __('Status'), ['for' => $this->buildHtmlTagName($controlName),]) .
                HtmlTagGeneratorHelper::tag('select', HtmlTagGeneratorHelper::renderSelectOptions($value, $statuses), ['id'   => $this->buildHtmlTagName($controlName),
                                                                                                                       'name' => $this->buildHtmlTagName($controlName),]);

        return $html;
    }

    /**
     * @return string
     */
    public function outdatedStateSelectRender()
    {
        $controlName = self::SUBMISSION_OUTDATE_STATE;

        $states = [
            0 => __('Up to Date'),
            1 => __('Outdated'),
        ];

        // add 'Any' to turn off filter
        $states = array_merge(['any' => __('Any')], $states);

        $value = $this->getFormElementValue($controlName, $this->defaultValues[$controlName]);

        $html = HtmlTagGeneratorHelper::tag('label', __('Content Status'), ['for' => $this->buildHtmlTagName($controlName),]) .
                HtmlTagGeneratorHelper::tag('select', HtmlTagGeneratorHelper::renderSelectOptions($value, $states), ['id'   => $this->buildHtmlTagName($controlName),
                                                                                                                     'name' => $this->buildHtmlTagName($controlName),]);

        return $html;
    }

    /**
     * @return string
     */
    public function lockedStateSelectRender()
    {
        $controlName = self::SUBMISSION_LOCKED_STATE;

        $states = [
            0 => __('Not locked'),
            1 => __('Locked'),
        ];

        // add 'Any' to turn off filter
        $states = array_merge(['any' => __('Any')], $states);

        $value = $this->getFormElementValue($controlName, $this->defaultValues[$controlName]);

        $html = HtmlTagGeneratorHelper::tag('label', __('Lock Status'), ['for' => $this->buildHtmlTagName($controlName),]) .
                HtmlTagGeneratorHelper::tag('select', HtmlTagGeneratorHelper::renderSelectOptions($value, $states), ['id'   => $this->buildHtmlTagName($controlName),
                                                                                                                     'name' => $this->buildHtmlTagName($controlName),]);

        return $html;
    }

    /**
     * @return string
     */
    public function clonedStateSelectRender()
    {
        $controlName = self::SUBMISSION_CLONED_STATE;

        $states = [
            0 => __('Translated'),
            1 => __('Cloned'),
        ];

        // add 'Any' to turn off filter
        $states = array_merge(['any' => __('Any')], $states);

        $value = $this->getFormElementValue($controlName, $this->defaultValues[$controlName]);

        $html = HtmlTagGeneratorHelper::tag('label', __('Clone Status'), ['for' => $this->buildHtmlTagName($controlName),]) .
                HtmlTagGeneratorHelper::tag('select', HtmlTagGeneratorHelper::renderSelectOptions($value, $states), ['id'   => $this->buildHtmlTagName($controlName),
                                                                                                                     'name' => $this->buildHtmlTagName($controlName),]);

        return $html;
    }

    public function renderSearchBox()
    {
        $html = HtmlTagGeneratorHelper::tag('label', __('Search'), ['for' => 's'])
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

        return $html;
    }

    /**
     * @return string
     */
    public function targetLocaleSelectRender()
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

        /**
         * @var Locale[] $locales
         */
        ArrayHelper::sortLocales($locales);

        $_locales = [
            'any' => __('Any'),
        ];

        foreach ($locales as $locale) {
            $_locales[$locale->getBlogId()] = $locale->getLabel();
        }

        $value = $this->getFormElementValue($controlName, $this->defaultValues[$controlName]);
        $html = HtmlTagGeneratorHelper::tag(
                'label',
                __('Target Site'),
                ['for' => $this->buildHtmlTagName($controlName)]
            ) . HtmlTagGeneratorHelper::tag(
                'select',
                HtmlTagGeneratorHelper::renderSelectOptions($value, $_locales),
                ['id' => $this->buildHtmlTagName($controlName), 'name' => $this->buildHtmlTagName($controlName)]
            );

        return $html;
    }


    /**
     * @return string
     */
    public function contentTypeSelectRender()
    {
        $controlName = 'content-type';

        $types = $this->getActiveContentTypes($this->entityHelper->getSiteHelper(), 'submissionBoard');

        // add 'Any' to turn off filter
        $types = array_merge(['any' => __('Any')], $types);

        $value = $this->getFormElementValue($controlName, $this->defaultValues[$controlName]);

        $html = HtmlTagGeneratorHelper::tag('label', __('Type'), ['for' => $this->buildHtmlTagName($controlName),]) .
                HtmlTagGeneratorHelper::tag('select', HtmlTagGeneratorHelper::renderSelectOptions($value, $types), ['id'   => $this->buildHtmlTagName($controlName),
                                                                                                                    'name' => $this->buildHtmlTagName($controlName),]);

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
        return $name;
    }
}