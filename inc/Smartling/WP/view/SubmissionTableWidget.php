<?php

namespace Smartling\WP\View;

use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Submissions\SubmissionManager;

/**
 * Class SubmissionTableWidget
 *
 * @package Smartling\WP\View
 */
class SubmissionTableWidget extends \WP_List_Table {

	/**
	 * @var string
	 */
	private $_custom_controls_namespace = 'smartling-submissions-page';

	/**
	 * the source array with request data
	 *
	 * @var array
	 */
	private $source;

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
	public function __construct ( SubmissionManager $manager ) {
		$this->manager = $manager;
		$this->source  = $_REQUEST;

		$this->defaultValues[ self::SUBMISSION_STATUS_SELECT_ELEMENT_NAME ] = $manager->getDefaultSubmissionStatus();

		parent::__construct( $this->_settings );
	}

	/**
	 * @param string $fieldNameKey
	 * @param string $orderDirectionKey
	 *
	 * @return array
	 */
	public function getSortingOptions ( $fieldNameKey = 'orderby', $orderDirectionKey = 'order' ) {
		$options = array ();

		$column = $this->getFromSource( $fieldNameKey, false );

		if ( false !== $column ) {
			$direction = strtoupper( $this->getFromSource( $orderDirectionKey,
				SmartlingToCMSDatabaseAccessWrapperInterface::SORT_OPTION_ASC ) );

			$options[] = array ( $column => $direction );
		}

		return $options;
	}

	function column_default ( $item, $column_name ) {
		switch ( $column_name ) {
			default:
				return $item[ $column_name ];
		}
	}

	function column_name ( $item ) {

		//Build row actions
		$actions = array (
			'send'     => sprintf( '<a href="?page=%s&action=%s&submission=%s">Send</a>', $_REQUEST['page'], 'send',
				$item['id'] ),
			'download' => sprintf( '<a href="?page=%s&action=%s&submission=%s">Download</a>', $_REQUEST['page'],
				'download', $item['id'] ),
		);

		//Return the title contents
		return sprintf( '%1$s <span style="color:silver">(id:%2$s)</span>%3$s',
			/*$1%s*/
			$item['sourceTitle'],
			/*$2%s*/
			$item['id'],
			/*$3%s*/
			$this->row_actions( $actions )
		);
	}

	function column_cb ( $item ) {
		return sprintf(
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
	public function get_columns () {
		return $this->manager->getColumnsLabels();
	}

	/**
	 * @inheritdoc
	 */
	public function get_sortable_columns () {

		$fields = $this->manager->getSortableFields();

		$sortable_columns = array ();

		foreach ( $fields as $field ) {
			$sortable_columns[ $field ] = array ( $field, false );
		}

		return $sortable_columns;
	}

	/**
	 * @inheritdoc
	 */
	public function get_bulk_actions () {
		$actions = array (
			'send'     => __( 'Send' ),
			'download' => __( 'Download' ),
			/*'generate_fake' => 'Generate',
			'update_last' => 'UPdate',*/
		);

		return $actions;
	}

	function process_bulk_action () {
		switch ( $this->current_action() ) {
			case "download":
				wp_die( 'Items downloading!' );
				break;
			case "send":
				wp_die( 'Items sending' );
				break;
			/*case "generate_fake":
			{

				$data = array (
				'id'                   => null,
				'sourceTitle'          => 'Automatic generated title',
				'sourceBlog'           => 1,
				'sourceContentHash'    => md5(''),
				'contentType'          => WordpressContentTypeHelper::CONTENT_TYPE_POST,
				'sourceGUID'           => '/ol"olo',
				'fileUri'              => "/tralala'",
				'targetLocale'         => 'es_US',
				'targetBlog'           => 5,
				'targetGUID'           => '',
				'submitter'            => 'admin',
				'submissionDate'       => time(),
				'approvedStringCount'  => 37,
				'completedStringCount' => 14,
				'status'               => 'New',
			);

				$entity =

				$this->manager->createSubmission($data);
				$this->manager->storeEntity($entity);



				break;
			}
			case "update_last":
			{
				$total = 0;
				$entities = $this->manager->getEntities(null, null, array(), null, $total);

				$data = $entities[0];

				$data->status = 'In Progress';

				$this->manager->storeEntity($data);

				//var_dump($data);




				break;
			}
			*/


		}
	}

	/**
	 * @return string|null
	 */
	private function getContentTypeFilterValue () {
		$value = $this->getFormElementValue(
			self::CONTENT_TYPE_SELECT_ELEMENT_NAME,
			$this->defaultValues[ self::CONTENT_TYPE_SELECT_ELEMENT_NAME ]
		);

		return 'any' === $value ? null : $value;
	}

	/**
	 * @return string|null
	 */
	private function getStatusFilterValue () {
		$value = $this->getFormElementValue(
			self::SUBMISSION_STATUS_SELECT_ELEMENT_NAME,
			$this->defaultValues[ self::SUBMISSION_STATUS_SELECT_ELEMENT_NAME ]
		);

		return 'any' === $value ? null : $value;
	}

	/**
	 * @inheritdoc
	 */
	public function prepare_items () {
		$pageOptions = array (
			'limit' => $this->manager->getPageSize(),
			'page'  => $this->get_pagenum()
		);

		$this->_column_headers = array (
			$this->get_columns(),
			array ( 'id' ),
			$this->get_sortable_columns()
		);

		$this->process_bulk_action();

		$total = 0;

		$contentTypeFilterValue = $this->getContentTypeFilterValue();

		$statusFilterValue = $this->getStatusFilterValue();

		$searchText = $this->getFromSource( 's', '' );

		if ( empty( $searchText ) ) {
			$data = $this->manager->getEntities( $contentTypeFilterValue, $statusFilterValue,
				$this->getSortingOptions(), $pageOptions,
				$total );
		} else {
			$data = $this->manager->search( $searchText, array ( 'sourceTitle', 'sourceGUID', 'fileUri' ),
				$contentTypeFilterValue, $statusFilterValue, $this->getSortingOptions(), $pageOptions,
				$total );
		}

		$dataAsArray = array ();

		foreach ( $data as $element ) {
			$dataAsArray[] = $element->toArray();
		}

		$this->items = $dataAsArray;

		$this->set_pagination_args( array (
			'total_items' => $total,
			'per_page'    => $pageOptions['limit'],
			'total_pages' => ceil( $total / $pageOptions['limit'] )
		) );
	}

	/**
	 * @return string
	 */
	public function statusSelectRender () {
		$controlName = 'status';

		$statuses = $this->manager->getSubmissionStatusLabels();

		// add 'Any' to turn off filter
		$statuses = array_merge( array ( 'any' => __( 'Any' ) ), $statuses );

		$value = $this->getFormElementValue(
			$controlName,
			$this->defaultValues[ $controlName ]
		);

		$html = HtmlTagGeneratorHelper::tag(
				'label',
				__( 'Status' ),
				array (
					'for' => $this->buildHtmlTagName( $controlName ),
				)
			) . HtmlTagGeneratorHelper::tag(
				'select',
				HtmlTagGeneratorHelper::renderSelectOptions(
					$value,
					$statuses
				),
				array (
					'id'   => $this->buildHtmlTagName( $controlName ),
					'name' => $this->buildHtmlTagName( $controlName )
				)
			);

		return $html;
	}

	/**
	 * @return string
	 */
	public function contentTypeSelectRender () {
		$controlName = 'content-type';

		$types = WordpressContentTypeHelper::getLabelMap();

		// add 'Any' to turn off filter
		$types = array_merge( array ( 'any' => __( 'Any' ) ), $types );

		$value = $this->getFormElementValue(
			$controlName,
			$this->defaultValues[ $controlName ]
		);

		$html = HtmlTagGeneratorHelper::tag(
				'label',
				__( 'Type' ),
				array (
					'for' => $this->buildHtmlTagName( $controlName ),
				)
			) . HtmlTagGeneratorHelper::tag(
				'select',
				HtmlTagGeneratorHelper::renderSelectOptions(
					$value,
					$types
				),
				array (
					'id'   => $this->buildHtmlTagName( $controlName ),
					'name' => $this->buildHtmlTagName( $controlName )
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
	public function renderSubmitButton ( $label ) {
		$id = $name = $this->buildHtmlTagName( 'go-and-filter' );

		$options = array (
			'id'    => $id,
			'name'  => '',
			'class' => 'button action',

		);

		return $inputHTMLHelper = HtmlTagGeneratorHelper::submitButton( $label, $options );
	}

	/**
	 * Retrieves from source array value for input element
	 *
	 * @param string $name
	 * @param mixed  $defaultValue
	 *
	 * @return mixed
	 */
	private function getFormElementValue ( $name, $defaultValue ) {
		return $this->getFromSource( $this->buildHtmlTagName( $name ), $defaultValue );
	}

	/**
	 * @param string $keyName
	 * @param mixed  $defaultValue
	 *
	 * @return mixed
	 */
	private function getFromSource ( $keyName, $defaultValue ) {
		return array_key_exists( $keyName, $this->source ) ? $this->source[ $keyName ] : $defaultValue;
	}

	/**
	 * Builds unique name attribute value for HTML Form element tag
	 *
	 * @param string $name
	 *
	 * @return string
	 */
	private function buildHtmlTagName ( $name ) {
		return $this->_custom_controls_namespace . '-' . $name;
	}
}