<?php

namespace Smartling\WP\View;

use Smartling\Helpers\Html\InputHelper;
use Smartling\Helpers\Html\SelectFilterHelper;
use Smartling\Submissions\SubmissionManager;

/**
 * Class SubmissionTableWidget
 * @package Smartling\WP\View
 */
class SubmissionTableWidget extends \WP_List_Table
{

    private $_custom_controls_namespace
        = 'smartling-submissions-page';

    private $source = null;

    /**
     * @var SelectFilterHelper
     */
    private $typeSelect = null;

    /**
     * @var SelectFilterHelper
     */
    private $statusSelect = null;

    private $_settings = array(
        'singular'  => 'submission',
        'plural'    => 'submissions',
        'ajax'      => false
    );

    /**
     * @var SubmissionManager $manager
     */
    private $manager;

    /**
     * @param SubmissionManager $manager
     */
    public function __construct(SubmissionManager $manager)
    {
        $this->manager = $manager;
        $this->source = $_POST;

        parent::__construct($this->_settings);
    }

    function column_default($item, $column_name){
        switch($column_name){
            default:
                return $item[$column_name];
        }
    }

    function column_name($item){

        //Build row actions
        $actions = array(
            'send'      => sprintf('<a href="?page=%s&action=%s&submission=%s">Send</a>', $_REQUEST['page'], 'send', $item['id']),
            'download'    => sprintf('<a href="?page=%s&action=%s&submission=%s">Download</a>', $_REQUEST['page'], 'download', $item['id']),
        );

        //Return the title contents
        return sprintf('%1$s <span style="color:silver">(id:%2$s)</span>%3$s',
            /*$1%s*/ $item['sourceTitle'],
            /*$2%s*/ $item['id'],
            /*$3%s*/ $this->row_actions($actions)
        );
    }

    function column_cb($item){
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            /*$1%s*/ $this->_args['singular'],  //Let's simply repurpose the table's singular label ("movie")
            /*$2%s*/ $item['id']                //The value of the checkbox should be the record's id
        );
    }

    /**
     * @inheritdoc
     */
    public function get_columns()
    {
        return $this->manager->getColumnsLabels();
    }

    /**
     * @inheritdoc
     */
    public function get_sortable_columns() {

        $fields = $this->manager->getSortableFields();

        $sortable_columns = array();

        foreach($fields as $field){
            $sortable_columns[$field] = array($field, false);
        }

        return $sortable_columns;
    }

    /**
     * @inheritdoc
     */
    public function get_bulk_actions() {
        $actions = array(
            'send'          => 'Send',
            'download'      => 'Download'
        );
        return $actions;
    }

    function process_bulk_action() {
        switch($this->current_action()) {
            case "download":
                wp_die('Items downloading!');
                break;
            case "send":
                wp_die('Items sending');
                break;
        }
    }

    /**
     * @inheritdoc
     */
    public function prepare_items() {

        $pageSize   = $this->manager->getPageSize();
        $pageNum    = $this->get_pagenum();

        $pageOptions = array(
            'limit' => $pageSize,
            'page'  => $pageNum
        );

        $this->_column_headers = array(
            $this->get_columns(),
            array('id'),
            $this->get_sortable_columns()
        );

        $this->process_bulk_action();

        $total = 0;

        $typeFilter = $this->getTypeSelect()->getValue('any');

        $typeFilter = 'any' === $typeFilter ? null : $typeFilter;

        $statusFilter = $this->getStatusSelect()->getValue('any');

        $statusFilter = 'any' === $statusFilter ? null : $statusFilter;

        $data = $this->manager->getEntities($typeFilter, $statusFilter, null, $pageOptions, $total);

        $dataAsArray = array();

        foreach($data as $element) {
            $dataAsArray[] = $element->toArray();
        }

        $data = $dataAsArray;

        if(count($data) > 1) {
            usort($data, array($this, 'usort_reorder'));
        }

        $this->items = $data;

        $this->set_pagination_args( array(
            'total_items' => $total,
            'per_page'    => $pageSize,
            'total_pages' => ceil($total/$pageSize)
        ) );
    }

    function usort_reorder($a,$b){
        $orderby = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'name'; //If no sort, default to title
        $order = (!empty($_REQUEST['order'])) ? $_REQUEST['order'] : 'asc'; //If no order, default to asc
        $result = strcmp($a[$orderby], $b[$orderby]); //Determine sort order
        return ($order==='asc') ? $result : -$result; //Send final sort direction to usort
    }

    /**
     * @return SelectFilterHelper
     */
    public function getStatusSelect()
    {
        if (is_null($this->statusSelect)) {
            $statuses_list = $this->manager->getSubmissionStatuses();
            $default = $this->manager->getDefaultSubmissionStatus();
            $statuses = array('any' => 'Any');
            foreach($statuses_list as $status){
                $statuses[$status] = $status;
            }

            $this->statusSelect = new SelectFilterHelper(
                $this->source,
                $this->_custom_controls_namespace,
                'status',
                'Status',
                $statuses,
                $default
            );
        }

        return $this->statusSelect;
    }

    /**
     * @return SelectFilterHelper
     */
    public function getTypeSelect()
    {
        if (is_null($this->typeSelect)) {
            $types = array_flip($this->manager->getHelper()->getReverseMap());

            $types = array_map('ucfirst', $types);

            $types = array_merge(array('any' => 'Any'), $types);

            $this->typeSelect = new SelectFilterHelper(
                $this->source,
                $this->_custom_controls_namespace,
                'content-type',
                'Type',
                $types,
                'any'
            );
        }

        return $this->typeSelect;
    }


    /**
     * Renders button
     * @param $label
     * @return string
     */
    public function renderJSSubmitButtion($label)
    {
        $inputHTMLHelper = new InputHelper(
            $this->source,
            $this->_custom_controls_namespace,
            'go-and-filter',
            $label,
            array(
                'type'      => 'submit',
                'class'     => 'button action'
            )
        );

        return $inputHTMLHelper->render();
    }




}