<?php

namespace Smartling\WP\View;

use Psr\Log\LoggerInterface;
use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\Helpers\CommonLogMessagesTrait;
use Smartling\Helpers\DateTimeHelper;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\EntityHelper;
use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Queue\Queue;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\WP\Controller\SmartlingListTable;

/**
 * Class SubmissionTableWidget
 * @package Smartling\WP\View
 */
class SubmissionTableWidget extends SmartlingListTable
{

    use CommonLogMessagesTrait;

    const ACTION_UPLOAD   = 'send';
    const ACTION_DOWNLOAD = 'download';

    /**
     * base name of Content-type filtering select
     */
    const CONTENT_TYPE_SELECT_ELEMENT_NAME = 'content-type';

    /**
     * base name of status filtering select
     */
    const SUBMISSION_STATUS_SELECT_ELEMENT_NAME = 'status';


    const SUBMISSION_OUTDATE_STATE = 'state';

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
     *
     * @var array
     */
    private $defaultValues = [
        self::CONTENT_TYPE_SELECT_ELEMENT_NAME      => 'any',
        self::SUBMISSION_STATUS_SELECT_ELEMENT_NAME => null,
        self::SUBMISSION_OUTDATE_STATE              => 'any',
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
            self::ACTION_UPLOAD   => __('Enqueue for Upload'),
            self::ACTION_DOWNLOAD => __('Enqueue for Download'),
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
                    case self::ACTION_UPLOAD:
                        foreach ($submissions as $submission) {
                            $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_NEW);
                            $this->manager->storeEntity($submission);
                        }
                        break;
                    case self::ACTION_DOWNLOAD:
                        foreach ($submissions as $submission) {
                            $this->getQueue()->enqueue([$submission->getId()], Queue::QUEUE_NAME_DOWNLOAD_QUEUE);
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
     * @inheritdoc
     */
    public function prepare_items()
    {
        $pageOptions = ['limit' => $this->manager->getPageSize(), 'page' => $this->get_pagenum(),];

        $this->_column_headers = [$this->get_columns(), ['id'], $this->get_sortable_columns(),];

        $this->processAction();

        $total = 0;

        $contentTypeFilterValue = $this->getContentTypeFilterValue();

        $statusFilterValue = $this->getStatusFilterValue();

        $outdatedFlag = $this->getOutdatedFlagFilterValue();

        $searchText = $this->getFromSource('s', '');

        if (empty($searchText)) {
            $data = $this->manager->getEntities($contentTypeFilterValue, $statusFilterValue, $outdatedFlag, $this->getSortingOptions(), $pageOptions, $total);
        } else {
            $data = $this->manager->search($searchText, ['source_title', 'source_id',
                                                         'file_uri'], $contentTypeFilterValue, $statusFilterValue, $this->getSortingOptions(), $pageOptions, $total);
        }

        $dataAsArray = [];

        $file_uri_max_chars = 50;

        foreach ($data as $element) {
            $row = $element->toArray();

            $row["file_uri"] = htmlentities($row["file_uri"]);
            $row['source_title'] = htmlentities($row['source_title']);
            $row['content_type'] = WordpressContentTypeHelper::getLocalizedContentType($row['content_type']);
            $row['submission_date'] = DateTimeHelper::toWordpressLocalDateTime(DateTimeHelper::stringToDateTime($row['submission_date']));
            $row['applied_date'] = '0000-00-00 00:00:00' === $row['applied_date'] ? __('Never')
                : DateTimeHelper::toWordpressLocalDateTime(DateTimeHelper::stringToDateTime($row['applied_date']));
            $row['target_locale'] = $this->entityHelper->getConnector()->getBlogNameByLocale($row['target_locale']);
            $row['outdated'] = 0 === $row['outdated'] ? '&nbsp;' : '&#10003;';

            if (mb_strlen($row['file_uri'], 'utf8') > $file_uri_max_chars) {
                $orig = $row['file_uri'];
                $shrinked = mb_substr($orig, 0, $file_uri_max_chars - 3, 'utf8') . '...';

                $row['file_uri'] = HtmlTagGeneratorHelper::tag('span', $shrinked, ['title' => $orig]);

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
    public function stateSelectRender()
    {
        $controlName = self::SUBMISSION_OUTDATE_STATE;

        $states = [
            0 => __('Up to Date'),
            1 => __('Outdated'),
        ];

        // add 'Any' to turn off filter
        $states = array_merge(['any' => __('Any')], $states);

        $value = $this->getFormElementValue($controlName, $this->defaultValues[$controlName]);

        $html = HtmlTagGeneratorHelper::tag('label', __('Outdated Flag'), ['for' => $this->buildHtmlTagName($controlName),]) .
                HtmlTagGeneratorHelper::tag('select', HtmlTagGeneratorHelper::renderSelectOptions($value, $states), ['id'   => $this->buildHtmlTagName($controlName),
                                                                                                                     'name' => $this->buildHtmlTagName($controlName),]);

        return $html;
    }


    /**
     * @return string
     */
    public function contentTypeSelectRender()
    {
        $controlName = 'content-type';

        $types = $this->getActiveContentTypes($this->entityHelper->getSiteHelper());

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
        return $this->_custom_controls_namespace . '-' . $name;
    }
}