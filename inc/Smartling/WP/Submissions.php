<?php
/**
 * Created by PhpStorm.
 * User: sergey@slepokurov.com
 * Date: 21.01.2015
 * Time: 0:07
 */

namespace Smartling\WP;


class Submissions extends WPAbstract implements WPHookInterface  {
    public function register() {
        add_action('admin_menu', array($this, 'menu'));
        add_action('network_admin_menu', array($this, 'menu'));
    }

    public function menu() {
        add_menu_page('Submissions', 'Smartling Connector', 'Administrator', 'smartling-submissions', array( $this, 'preView')  );
    }


    public function preView() {
        $table = new SubmissionTable();
        $this->view($table);
    }
}

class SubmissionTable extends \WP_List_Table {
    var $example_data = array(
        array(
            'id'             => '1',
            'post_id'        => '4',
            'name'           => 'Demo',
            'type'           => 'post',
            'locale'         => 'ru',
            'status'         => 'pending',
            'progress'       => '55',
            'submittedAt'    =>  null,
            'submitter'      => 'admin',
            'appliedAt'      => null,
            'applier'        => null
        ),
        array(
            'id'             => '2',
            'post_id'        => '4',
            'name'           => 'Demo 2',
            'type'           => 'post',
            'locale'         => 'de',
            'status'         => 'pending',
            'progress'       => '66',
            'submittedAt'    =>  null,
            'submitter'      => 'admin',
            'appliedAt'      => null,
            'applier'        => null
        )
    );


    function __construct(){
        global $status, $page;

        parent::__construct( array(
            'singular'  => 'submission',
            'plural'    => 'submissions',
            'ajax'      => false
        ) );
    }

    function column_default($item, $column_name){
        switch($column_name){
            default:
                return $item[$column_name];
        }
    }

    function column_title($item){

        //Build row actions
        $actions = array(
            'send'      => sprintf('<a href="?page=%s&action=%s&submission=%s">Send</a>', $_REQUEST['page'], 'send', $item['id']),
            'download'    => sprintf('<a href="?page=%s&action=%s&submission=%s">Download</a>', $_REQUEST['page'], 'download', $item['id']),
        );

        //Return the title contents
        return sprintf('%1$s <span style="color:silver">(id:%2$s)</span>%3$s',
            /*$1%s*/ $item['name'],
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

    function get_columns(){
        $columns = array(
            'cb'        => '<input type="checkbox" />', //Render a checkbox instead of text
            'post_id'        => 'Post ID',
            'name'           => 'Name',
            'type'           => 'Type',
            'locale'         => 'Locale',
            'status'         => 'Status',
            'progress'       => 'Progress',
            'submittedAt'    => 'Submitted at',
            'submitter'      => 'Submitter',
            'appliedAt'      => 'Applied at',
            'applier'        => 'Applier'
        );
        return $columns;
    }

    function get_sortable_columns() {
        $sortable_columns = array(
            'name'     => array('name',false),     //true means it's already sorted
            'type'    => array('type',false),
            'status'  => array('status',false)
        );
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
        global $wpdb;
        $per_page = 5;

        $columns = $this->get_columns();

        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);

        $this->process_bulk_action();

        $data = $this->example_data;


        if(count($data) > 1) {
            usort($data, array($this, 'usort_reorder'));
        }

        $current_page = $this->get_pagenum();

        $total_items = count($data);

        $data = array_slice($data,(($current_page-1)*$per_page),$per_page);

        $this->items = $data;

        $this->set_pagination_args( array(
            'total_items' => $total_items,                  //WE have to calculate the total number of items
            'per_page'    => $per_page,                     //WE have to determine how many items to show on a page
            'total_pages' => ceil($total_items/$per_page)   //WE have to calculate the total number of pages
        ) );
    }

    function usort_reorder($a,$b){
        $orderby = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'name'; //If no sort, default to title
        $order = (!empty($_REQUEST['order'])) ? $_REQUEST['order'] : 'asc'; //If no order, default to asc
        $result = strcmp($a[$orderby], $b[$orderby]); //Determine sort order
        return ($order==='asc') ? $result : -$result; //Send final sort direction to usort
    }
}