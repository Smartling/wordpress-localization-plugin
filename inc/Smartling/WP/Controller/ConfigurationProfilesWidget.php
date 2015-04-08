<?php

namespace Smartling\WP\Controller;

use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;

class ConfigurationProfilesWidget extends \WP_List_Table {

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

	private $_settings = array (
		'singular' => 'profile',
		'plural'   => 'profiles',
		'ajax'     => false
	);

	/**
	 * @var SettingsManager $manager
	 */
	private $manager;

	public function __construct ( SettingsManager $manager ) {
		$this->manager = $manager;
		$this->source  = $_REQUEST;

		parent::__construct( $this->_settings );
	}

	public function getSortingOptions ( $fieldNameKey = 'orderby', $orderDirectionKey = 'order' ) {
		$options = array ();

		$column = $this->getFromSource( $fieldNameKey, false );

		if ( false !== $column ) {
			$direction = strtoupper( $this->getFromSource( $orderDirectionKey,
				SmartlingToCMSDatabaseAccessWrapperInterface::SORT_OPTION_ASC ) );

			$options = array ( $column => $direction );
		}

		return $options;
	}

	public function column_default ( $item, $column_name ) {
		switch ( $column_name ) {
			default:
				return $item[ $column_name ];
		}
	}

	/**
	 * @param $item
	 *
	 * @return string
	 */
	public function applyRowActions ( $item ) {

		$linkTemplate = '?page=%s&action=%s&' . $this->buildHtmlTagName( $this->_args['singular'] ) . '=%s';

		//Build row actions
		$actions = array (
			'edit'     => HtmlTagGeneratorHelper::tag( 'a', __( 'Edit' ), array (
				'href' => vsprintf( $linkTemplate, array ( $_REQUEST['page'], 'edit', $item['id'] ) )
			) ),
			/*'delete' => HtmlTagGeneratorHelper::tag( 'a', __( 'Delete' ), array (
				'href' => vsprintf( $linkTemplate, array ( $_REQUEST['page'], 'delete', $item['id'] ) )
			) ),*/
		);

		//Return the title contents
		return vsprintf( '%s %s', array ( $item['profile_name'], $this->row_actions( $actions ) ) );
	}

	/**
	 * Generates a checkbox for a row to add row to bulk actions
	 *
	 * @param array $item
	 *
	 * @return string
	 */
	public function column_cb ( array $item ) {
		return HtmlTagGeneratorHelper::tag( 'input', '', array (
			'type'  => 'checkbox',
			'name'  => $this->buildHtmlTagName( $this->_args['singular'] ) . '[]',
			'value' => $item['id'],
			'id'    => 'submission-id-' . $item['id'],
			'class' => 'bulkaction',
		) );
	}

	/**
	 * @inheritdoc
	 */
	public function get_columns () {
		$columns = ConfigurationProfileEntity::getFieldLabels();
		//$columns = array_merge( array ( 'bulkActionCb' => '<input type="checkbox" class="checkall" />' ), $columns );
		return $columns;
	}

	/**
	 * @inheritdoc
	 */
	public function get_sortable_columns () {
		$fields = ConfigurationProfileEntity::getSortableFields();
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
		return array();
	}

	/**
	 * Handles actions for single object
	 */
	private function processSingleAction () {
		$submissionId = (int) $this->getFormElementValue( 'profile', 0 );
		if ( $submissionId > 0 ) {
			switch ( $this->current_action() ) {
				case 'downloadSingle':
					break;
			}
		}
	}

	/**
	 * Handles actions
	 */
	private function processAction () {
		$this->processSingleAction();
	}

	public function renderNewProfileButton()
	{

		$options = array (
			'id'    => $this->buildHtmlTagName( 'createNew' ),
			'name'  => '',
			'class' => 'button action',
			'type'=>'submit',
			'value'=>__('Add Profile'),

		);

		return HtmlTagGeneratorHelper::tag('input', '', $options);
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

		$this->processAction();

		$total = 0;

		$data = $this->manager->getEntities(array(), null, $total);

		$dataAsArray = array ();

		foreach ( $data as $element ) {
			$row = $element->toArray();

			$row['profile_name']    = $this->applyRowActions( $row );

			$dataAsArray[] = $row;
		}

		$this->items = $dataAsArray;

		$this->set_pagination_args( array (
			'total_items' => $total,
			'per_page'    => $pageOptions['limit'],
			'total_pages' => ceil( $total / $pageOptions['limit'] )
		) );
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