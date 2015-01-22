<?php

namespace Smartling\WP\View;

use Smartling\Submissions\SubmissionManager;

/**
 * Class SubmissionTableWidget
 * @package Smartling\WP\View
 */
class SubmissionTableWidget extends \WP_List_Table
{



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

    function get_sortable_columns() {

        $fields = $this->manager->getSortableFields();

        $sortable_columns = array();

        foreach($fields as $field){
            $sortable_columns[$field] = array($field, false);
        }

        return $sortable_columns;
    }

    function get_bulk_actions() {
        $actions = array(
            'send'    => 'Send',
            'download'    => 'Download'
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

    function prepare_items() {

        $pageSize   = $this->manager->getPageSize();
        $pageNum    = $this->get_pagenum();

        $pageOptions = array('limit' => $pageSize, 'page' => $pageNum);

        $columns = $this->get_columns();

        $hidden = array();

        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array($columns, $hidden, $sortable);

        $this->process_bulk_action();

        $total = 0;

        $data = $this->manager->getEntities(null, null, null, $pageOptions, $total);

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
}