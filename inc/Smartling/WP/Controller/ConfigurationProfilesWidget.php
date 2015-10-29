<?php

namespace Smartling\WP\Controller;

use Smartling\Bootstrap;
use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\Exception\BlogNotFoundException;
use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;

/**
 * Class ConfigurationProfilesWidget
 *
 * @package Smartling\WP\Controller
 */
class ConfigurationProfilesWidget extends \WP_List_Table {

	/**
	 * @var string
	 */
	private $_custom_controls_namespace = 'smartling-profile';

	/**
	 * the source array with request data
	 *
	 * @var array
	 */
	private $source;


	private $_settings = [
		'singular' => 'profile',
		'plural'   => 'profiles',
		'ajax'     => false,
	];

	/**
	 * @var SettingsManager $manager
	 */
	private $manager;

	/**
	 * @param SettingsManager $manager
	 */
	public function __construct ( SettingsManager $manager ) {
		$this->manager = $manager;
		$this->source  = $_REQUEST;
		parent::__construct( $this->_settings );
	}

	/**
	 * @param string $fieldNameKey
	 * @param string $orderDirectionKey
	 *
	 * @return array
	 */
	public function getSortingOptions ( $fieldNameKey = 'orderby', $orderDirectionKey = 'order' ) {
		$options = [ ];
		$column  = $this->getFromSource( $fieldNameKey, false );
		if ( false !== $column ) {
			$direction = strtoupper( $this->getFromSource( $orderDirectionKey,
				SmartlingToCMSDatabaseAccessWrapperInterface::SORT_OPTION_ASC ) );

			$options = [ $column => $direction ];
		}

		return $options;
	}

	/**
	 * @param $item
	 * @param $column_name
	 *
	 * @return mixed
	 */
	public function column_default ( $item, $column_name ) {
		return $item[ $column_name ];
	}

	/**
	 * @param $item
	 *
	 * @return string
	 */
	public function applyRowActions ( $item ) {

		$linkTemplate = '?page=%s&action=%s&' . $this->_args['singular'] . '=%s';

		//Build row actions
		$actions = [
			'edit' => HtmlTagGeneratorHelper::tag( 'a', __( 'Edit' ), [
				'href' => vsprintf( $linkTemplate,
					[ 'smartling_configuration_profile_setup', 'edit', $item['id'] ] ),
			] ),
			/*'delete' => HtmlTagGeneratorHelper::tag( 'a', __( 'Delete' ), array (
				'href' => vsprintf( $linkTemplate, array ( $_REQUEST['page'], 'delete', $item['id'] ) )
			) ),*/
		];

		//Return the title contents
		return vsprintf( '%s %s', [ esc_html__( $item['profile_name'] ), $this->row_actions( $actions ) ] );
	}

	/**
	 * Generates a checkbox for a row to add row to bulk actions
	 *
	 * @param array $item
	 *
	 * @return string
	 */
	protected function column_cb ( $item ) {
		return HtmlTagGeneratorHelper::tag( 'input', '', [
			'type'  => 'checkbox',
			'name'  => $this->buildHtmlTagName( $this->_args['singular'] ) . '[]',
			'value' => $item['id'],
			'id'    => 'submission-id-' . $item['id'],
			'class' => 'bulkaction',
		] );
	}

	/**
	 * @inheritdoc
	 */
	public function get_columns () {
		return ConfigurationProfileEntity::getFieldLabels();;
	}

	/**
	 * @inheritdoc
	 */
	public function get_sortable_columns () {
		$fields           = ConfigurationProfileEntity::getSortableFields();
		$sortable_columns = [ ];
		foreach ( $fields as $field ) {
			$sortable_columns[ $field ] = [ $field, true ];
		}

		return $sortable_columns;
	}

	/**
	 * @inheritdoc
	 */
	public function get_bulk_actions () {
		return [ ];
	}

	public function renderNewProfileButton () {

		$options = [
			'id'    => $this->buildHtmlTagName( 'createNew' ),
			'name'  => '',
			'class' => 'button action',
			'type'  => 'submit',
			'value' => __( 'Add Profile' ),

		];

		return HtmlTagGeneratorHelper::tag( 'input', '', $options );
	}

	/**
	 * @inheritdoc
	 */
	public function prepare_items () {
		$pageOptions = [
			'limit' => $this->manager->getPageSize(),
			'page'  => $this->get_pagenum(),
		];

		$this->_column_headers = [
			$this->get_columns(),
			[ 'id' ],
			$this->get_sortable_columns(),
		];

		$total = 0;

		$data = $this->manager->getEntities( [ ], null, $total );

		$dataAsArray = [ ];
		$types       = ConfigurationProfileEntity::getRetrievalTypes();
		foreach ( $data as $element ) {

			$row = $element->toArray();

			$row['profile_name']   = $this->applyRowActions( $row );
			$row['api_key']        = mb_substr( $row['api_key'], 0, 9, 'utf8' ) . '...';
			$row['is_active']      = 0 < $row['is_active'] ? __( 'Yes' ) : __( 'No' );
			$row['auto_authorize'] = 0 < $row['auto_authorize'] ? __( 'Yes' ) : __( 'No' );

			$row['retrieval_type'] = $types[ $row['retrieval_type'] ];
			try {
				$row['original_blog_id'] =
					$this->manager->getSiteHelper()->getBlogLabelById( $this->manager->getPluginProxy(),
						$row['original_blog_id']
					);
			} catch ( BlogNotFoundException $e ) {
				//Bootstrap::DebugPrint($e,true);
				$row['original_blog_id'] = 0;
				$row['profile_name']     = __( vsprintf( '[Broken profile] ', [ ] ) ) . $row['profile_name'];
			}

			$dataAsArray[] = $row;

		}

		$this->items = $dataAsArray;

		$this->set_pagination_args( [
			'total_items' => $total,
			'per_page'    => $pageOptions['limit'],
			'total_pages' => ceil( $total / $pageOptions['limit'] ),
		] );
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