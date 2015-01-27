<?php

namespace Smartling\WP\View;

use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Submissions\SubmissionManager;

/**
 * Class SubmissionTableWidget
 *
 * @package Smartling\WP\View
 */
class SubmissionTableWidget extends \WP_List_Table
{

    /**
     * @var string
     */
    private $_custom_controls_namespace
        = 'smartling-submissions-page';

    /**
     * the source array with request data
     *
     * @var array
     */
    private $source = null;

    /**
     * base name of Content-type filtering select
     */
    const CONTENT_TYPE_SELECT_ELEMENT_NAME = 'content-type';

    /**
     * base name of status filtering select
     */
    const SUBMISSION_STATUS_SELECT_ELEMENT_NAME = 'status';

    /**
     * default values of custom form elements on page
     *
     * @var array
     */
    private $defaultValues = array (
        self::CONTENT_TYPE_SELECT_ELEMENT_NAME      => 'any',
        self::SUBMISSION_STATUS_SELECT_ELEMENT_NAME => null,
    );

    private $_settings = array (
        'singular' => 'submission',
        'plural'   => 'submissions',
        'ajax'     => false
    );

    /**
     * @var SubmissionManager $manager
     */
    private $manager;

    /**
     * @param SubmissionManager $manager
     */
    public function __construct (SubmissionManager $manager)
    {
        $this->manager = $manager;
        $this->source = $_POST;

        $this->defaultValues[self::SUBMISSION_STATUS_SELECT_ELEMENT_NAME] = $manager->getDefaultSubmissionStatus ();

        parent::__construct ($this->_settings);
    }

    function column_default ($item, $column_name)
    {
        switch ($column_name) {
            default:
                return $item[$column_name];
        }
    }

    function column_name ($item)
    {

        //Build row actions
        $actions = array (
            'send'     => sprintf ('<a href="?page=%s&action=%s&submission=%s">Send</a>', $_REQUEST['page'], 'send',
                $item['id']),
            'download' => sprintf ('<a href="?page=%s&action=%s&submission=%s">Download</a>', $_REQUEST['page'],
                'download', $item['id']),
        );

        //Return the title contents
        return sprintf ('%1$s <span style="color:silver">(id:%2$s)</span>%3$s',
            /*$1%s*/
            $item['sourceTitle'],
            /*$2%s*/
            $item['id'],
            /*$3%s*/
            $this->row_actions ($actions)
        );
    }

    function column_cb ($item)
    {
        return sprintf (
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            /*$1%s*/
            $this->_args['singular'],  //Let's simply repurpose the table's singular label ("movie")
            /*$2%s*/
            $item['id']                //The value of the checkbox should be the record's id
        );
    }

    /**
     * @inheritdoc
     */
    public function get_columns ()
    {
        return $this->manager->getColumnsLabels ();
    }

    /**
     * @inheritdoc
     */
    public function get_sortable_columns ()
    {

        $fields = $this->manager->getSortableFields ();

        $sortable_columns = array ();

        foreach ($fields as $field) {
            $sortable_columns[$field] = array ($field, false);
        }

        return $sortable_columns;
    }

    /**
     * @inheritdoc
     */
    public function get_bulk_actions ()
    {
        $actions = array (
            'send'     => 'Send',
            'download' => 'Download'
        );

        return $actions;
    }

    function process_bulk_action ()
    {
        switch ($this->current_action ()) {
            case "download":
                wp_die ('Items downloading!');
                break;
            case "send":
                wp_die ('Items sending');
                break;
        }
    }

    /**
     * @inheritdoc
     */
    public function prepare_items ()
    {

        $pageSize = $this->manager->getPageSize ();
        $pageNum = $this->get_pagenum ();

        $pageOptions = array (
            'limit' => $pageSize,
            'page'  => $pageNum
        );

        $this->_column_headers = array (
            $this->get_columns (),
            array ('id'),
            $this->get_sortable_columns ()
        );

        $this->process_bulk_action ();

        $total = 0;

        $contentTypeFilterValue = $this->getFormElementValue (
            self::CONTENT_TYPE_SELECT_ELEMENT_NAME,
            $this->defaultValues[self::CONTENT_TYPE_SELECT_ELEMENT_NAME]
        );

        $contentTypeFilterValue = 'any' === $contentTypeFilterValue ? null : $contentTypeFilterValue;

        $statusFilterValue = $this->getFormElementValue (
            self::SUBMISSION_STATUS_SELECT_ELEMENT_NAME,
            $this->defaultValues[self::SUBMISSION_STATUS_SELECT_ELEMENT_NAME]
        );

        $statusFilterValue = 'any' === $statusFilterValue ? null : $statusFilterValue;

        $data = $this->manager->getEntities ($contentTypeFilterValue, $statusFilterValue, array(), $pageOptions, $total);

        $dataAsArray = array ();

        foreach ($data as $element) {
            $dataAsArray[] = $element->toArray ();
        }

        $data = $dataAsArray;

        if (count ($data) > 1) {
            usort ($data, array ($this, 'usort_reorder'));
        }

        $this->items = $data;

        $this->set_pagination_args (array (
            'total_items' => $total,
            'per_page'    => $pageSize,
            'total_pages' => ceil ($total / $pageSize)
        ));
    }

    function usort_reorder ($a, $b)
    {
        $orderby = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'name'; //If no sort, default to title
        $order = (!empty($_REQUEST['order'])) ? $_REQUEST['order'] : 'asc'; //If no order, default to asc
        $result = strcmp ($a[$orderby], $b[$orderby]); //Determine sort order
        return ($order === 'asc') ? $result : - $result; //Send final sort direction to usort
    }

    /**
     * @return string
     */
    public function statusSelectRender ()
    {
        $controlName = 'status';

        $statuses = $this->manager->getSubmissionStatusLabels ();

        // add 'Any' to turn off filter
        $statuses = array_merge (array ('any' => __ ('Any')), $statuses);

        $value = $this->getFormElementValue (
            $controlName,
            $this->defaultValues[$controlName]
        );

        $html = HtmlTagGeneratorHelper::tag (
                'label',
                __ ('Status'),
                array (
                    'for' => $this->buildHtmlTagName ($controlName),
                )
            ) . HtmlTagGeneratorHelper::tag (
                'select',
                HtmlTagGeneratorHelper::renderSelectOptions (
                    $value,
                    $statuses
                ),
                array (
                    'id'   => $this->buildHtmlTagName ($controlName),
                    'name' => $this->buildHtmlTagName ($controlName)
                )
            );

        return $html;
    }

    /**
     * @return string
     */
    public function contentTypeSelectRender ()
    {
        $controlName = 'content-type';

        $types = WordpressContentTypeHelper::getLabelMap ();

        // add 'Any' to turn off filter
        $types = array_merge (array ('any' => __ ('Any')), $types);

        $value = $this->getFormElementValue (
            $controlName,
            $this->defaultValues[$controlName]
        );

        $html = HtmlTagGeneratorHelper::tag (
                'label',
                __ ('Type'),
                array (
                    'for' => $this->buildHtmlTagName ($controlName),
                )
            ) . HtmlTagGeneratorHelper::tag (
                'select',
                HtmlTagGeneratorHelper::renderSelectOptions (
                    $value,
                    $types
                ),
                array (
                    'id'   => $this->buildHtmlTagName ($controlName),
                    'name' => $this->buildHtmlTagName ($controlName)
                )
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
    public function renderSubmitButton ($label)
    {
        $id = $name = $this->buildHtmlTagName ('go-and-filter');

        $options = array (
            'id'    => $id,
            'name'  => $name,
            'class' => 'button action',

        );

        return $inputHTMLHelper = HtmlTagGeneratorHelper::submitButton ($label, $options);
    }

    /**
     * Retrieves from source array value for input element
     *
     * @param string $name
     * @param mixed  $defaultValue
     *
     * @return mixed
     */
    private function getFormElementValue ($name, $defaultValue)
    {
        return isset($this->source[$this->buildHtmlTagName ($name)])
            ? $this->source[$this->buildHtmlTagName ($name)]
            : $defaultValue;
    }

    /**
     * Builds unique name attribute value for HTML Form element tag
     *
     * @param string $name
     *
     * @return string
     */
    private function buildHtmlTagName ($name)
    {
        return $this->_custom_controls_namespace . '-' . $name;
    }
}