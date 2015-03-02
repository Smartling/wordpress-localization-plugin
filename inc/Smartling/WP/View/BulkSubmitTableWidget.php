<?php
/**
 * Created by PhpStorm.
 * User: sergey@slepokurov.com
 * Date: 02.03.2015
 * Time: 11:44
 */

namespace Smartling\WP\View;


use Smartling\Base\SmartlingCore;
use Smartling\Bootstrap;
use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\Helpers\EntityHelper;
use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Helpers\PluginInfo;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Submissions\SubmissionManager;

/**
 * Class BulkSubmitTableWidget
 *
 * @package Smartling\WP\View
 */
class BulkSubmitTableWidget  extends \WP_List_Table {

	/**
	 * @var string
	 */
	private $_custom_controls_namespace = 'smartling-bulk-submit-page';

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
		self::CONTENT_TYPE_SELECT_ELEMENT_NAME      => WordpressContentTypeHelper::CONTENT_TYPE_POST,
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
	 * @var EntityHelper
	 */
	private $entityHelper;

	/**
	 * @var PluginInfo
	 */
	private $pluginInfo;

	/**
	 * @param SubmissionManager $manager
	 * @param PluginInfo        $pluginInfo
	 * @param EntityHelper      $entityHelper
	 */
	public function __construct (
		SubmissionManager $manager,
		PluginInfo $pluginInfo,
		EntityHelper $entityHelper ) {

		$this->manager = $manager;
		$this->source  = $_REQUEST;
		$this->pluginInfo   = $pluginInfo;
		$this->entityHelper = $entityHelper;

		$this->defaultValues[ self::SUBMISSION_STATUS_SELECT_ELEMENT_NAME ] = self::CONTENT_TYPE_SELECT_ELEMENT_NAME;

		parent::__construct( $this->_settings );
	}

	/**
	 * @return EntityHelper
	 */
	public function getEntityHelper () {
		return $this->entityHelper;
	}

	/**
	 * @return PluginInfo
	 */
	public function getPluginInfo () {
		return $this->pluginInfo;
	}

	/**
	 * @param string $fieldNameKey
	 * @param string $orderDirectionKey
	 *
	 * @return array
	 */
	public function getSortingOptions ( $fieldNameKey = 'orderby', $orderDirectionKey = 'order' ) {
		$column = $this->getFromSource( $fieldNameKey, false );

		if ( false !== $column ) {
			$direction = strtoupper( $this->getFromSource( $orderDirectionKey,
				SmartlingToCMSDatabaseAccessWrapperInterface::SORT_OPTION_ASC ) );
		}

		return array(
			"orderby" => $column,
			"order" => $direction
		);
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
			'send'     => HtmlTagGeneratorHelper::tag( 'a', __( 'Send' ), array (
				'href' => vsprintf( $linkTemplate, array ( $_REQUEST['page'], 'sendSingle', $item['id'] . '-' . $item["type"] ) )
			) ),
		);

		//Return the title contents
		return vsprintf( '%s %s', array ( $item['title'], $this->row_actions( $actions ) ) );
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
			'value' => $item['id'] . '-' . $item["type"],
			'id' => $item['id'] . '-' . $item["type"]
		) );
	}

	/**
	 * @inheritdoc
	 */
	public function get_columns () {
		return array(
			'bulkActionCb' => "",
			'id'             => __( 'ID' ),
			'title'    => __( 'Title' ),
			'type'    => __( 'Type' ),
			'author'      => __( 'Author' ),
			'status' => __( 'Status' ),
			'locales'    => __( 'Locales' ),
			'updated'      => __( 'Updated' )
		);
	}

	/**
	 * @inheritdoc
	 */
	public function get_sortable_columns () {

		$fields = array(
			"title",
			"status",
			"author",
			"updated"
		);

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
		);

		return $actions;
	}

	/**
	 * Handles actions for multiply objects
	 */
	private function processBulkAction () {
		//var_dump('YO');die();
		/**
		 * @var array $submissions
		 */
		$submissions = $this->getFormElementValue( 'submission', array () );
		$locales = array();
		$data = $_REQUEST[ "bulk-submit-locales" ];

		if ( null !== $data && array_key_exists( 'locales', $data ) ) {
			foreach ( $data['locales'] as $blogId => $blogName ) {
				if ( array_key_exists( 'enabled', $blogName ) && 'on' === $blogName['enabled'] ) {
					$locales[ $blogId ] = $blogName['locale'];
				}
			}

			/**
			 * @var SmartlingCore $ep
			 */
			$ep = Bootstrap::getContainer()->get( 'entrypoint' );

			if ( is_array( $submissions ) && count( $locales ) > 0 ) {
				foreach ( $submissions as $submission ) {
					switch ( $this->current_action() ) {
						case "send":
							list( $id, $type ) = explode( '-', $submission );
							$sourceBlog = $this->getPluginInfo()->getSettingsManager()->getLocales()->getDefaultBlog();
							$originalId = (int) $this->getEntityHelper()->getOriginalContentId( $id );

							foreach ( $locales as $blogId => $blogName ) {

								$result = $ep->createForTranslation(
									$type,
									$sourceBlog,
									$originalId,
									(int) $blogId,
									$this->getEntityHelper()->getTarget( $id, $blogId )
								);
							}
							break;
					}
				}
			}
		}
	}

	/**
	 * Handles actions for single object
	 */
	private function processSingleAction () {
		$submissionId = (int) $this->getFormElementValue( 'submission', 0 );
		$locales = array();
		$data = $_REQUEST[ "bulk-submit-locales" ];
		if ( null !== $data && array_key_exists( 'locales', $data ) ) {
			foreach ( $data['locales'] as $blogId => $blogName ) {
				if ( array_key_exists( 'enabled', $blogName ) && 'on' === $blogName['enabled'] ) {
					$locales[ $blogId ] = $blogName['locale'];
				}
			}

			if ( $submissionId > 0 && count( $locales ) > 0 ) {
				/**
				 * @var SmartlingCore $ep
				 */
				$ep = Bootstrap::getContainer()->get( 'entrypoint' );

				switch ( $this->current_action() ) {
					case "sendSingle":
						list( $id, $type ) = explode( '-', $submissionId );

						$sourceBlog = $this->getPluginInfo()->getSettingsManager()->getLocales()->getDefaultBlog();
						$originalId = (int) $this->getEntityHelper()->getOriginalContentId( $id );

						foreach ( $locales as $blogId => $blogName ) {

							$result = $ep->createForTranslation(
								$type,
								$sourceBlog,
								$originalId,
								(int) $blogId,
								$this->getEntityHelper()->getTarget( $id, $blogId )
							);
						}
						break;
				}
			}
		}

		// TODO: ? where to show error messages?
	}

	/**
	 * Handles actions
	 */
	private function processAction () {
		$this->processBulkAction();
		$this->processSingleAction();
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


		$contentTypeFilterValue = $this->getContentTypeFilterValue();
		$sortOptions = $this->getSortingOptions();

		$core = Bootstrap::getContainer()->get( 'entrypoint' );
		$io = $core->getContentIoFactory()->getMapper( $contentTypeFilterValue );
		$data = $io->getAll(
			$pageOptions['limit'],
			($pageOptions['page'] - 1) * $pageOptions['limit'],
			$sortOptions["orderby"],
			$sortOptions["order"]
		);
		$total = $io->getTotal();

		$dataAsArray = array ();
		if($data) {
			foreach ( $data as $item ) {
				$row = $this->extractFields($item, $contentTypeFilterValue);
				//$row['title']  = $this->applyRowActions( $row );
				$row           = array_merge( array ( 'bulkActionCb' => $this->column_cb( $row ) ), $row );
				$dataAsArray[] = $row;
			}
		}


		$this->items = $dataAsArray;

		$this->set_pagination_args( array (
			'total_items' => $total,
			'per_page'    => $pageOptions['limit'],
			'total_pages' => ceil( $total / $pageOptions['limit'] )
		) );
	}

	/**
	 * @param $item
	 * @param $type
	 *
	 * @return array
	 */
	private function extractFields($item, $type) {
		switch($type) {
			case WordpressContentTypeHelper::CONTENT_TYPE_POST:
			case WordpressContentTypeHelper::CONTENT_TYPE_PAGE:
				return  array (
					"id"      => $item->ID,
					"title"   => $item->post_title,
					"type"    => $item->post_type,
					"author"  => get_the_author_meta( "user_nicename", $item->post_author ),
					"status"  => $item->post_status,
					"locales" => null,
					"updated" => $item->post_date
				);
			case WordpressContentTypeHelper::CONTENT_TYPE_POST_TAG:
			case WordpressContentTypeHelper::CONTENT_TYPE_CATEGORY:
				return  array (
					"id"      => $item->term_id,
					"title"   => $item->name,
					"type"    => $item->taxonomy,
					"author"  => get_the_author_meta( "user_nicename", $item->post_author ),
					"status"  => null,
					"locales" => null,
					"updated" => null
				);
		}
		return array();
	}

	/**
	 * @return string
	 */
	public function contentTypeSelectRender () {
		$controlName = 'content-type';

		$types = WordpressContentTypeHelper::getLabelMap();

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

		return HtmlTagGeneratorHelper::submitButton( $label, $options );
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