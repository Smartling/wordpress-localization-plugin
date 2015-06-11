<?php

namespace Smartling\WP\View;

use Exception;
use Smartling\Base\SmartlingCore;
use Smartling\Bootstrap;
use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\Exception\EntityNotFoundException;
use Smartling\Exception\SmartlingDbException;
use Smartling\Helpers\DateTimeHelper;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\EntityHelper;
use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Submissions\SubmissionManager;
use Smartling\WP\Controller\SmartlingListTable;

/**
 * Class SubmissionTableWidget
 *
 * @package Smartling\WP\View
 */
class SubmissionTableWidget extends SmartlingListTable {

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
	 * @var EntityHelper
	 */
	private $entityHelper;

	/**
	 * @param SubmissionManager $manager
	 * @param EntityHelper      $entityHelper
	 */
	public function __construct ( SubmissionManager $manager, EntityHelper $entityHelper ) {
		$this->manager      = $manager;
		$this->source       = $_REQUEST;
		$this->entityHelper = $entityHelper;

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

		$hrefFilters = vsprintf(
			'&%s=%s&%s=%s',
			array (
				$this->buildHtmlTagName( 'content-type' ),
				$this->getFormElementValue( 'content-type', $this->defaultValues['content-type'] ),
				$this->buildHtmlTagName( 'status' ),
				$this->getFormElementValue( 'status', $this->defaultValues['status'] ),
			)
		);

		//Build row actions
		$actions = array (
			'send'     => HtmlTagGeneratorHelper::tag( 'a', __( 'Resend' ), array (
				'href' => vsprintf( $linkTemplate . $hrefFilters,
					array ( $_REQUEST['page'], 'sendSingle', $item['id'] ) )
			) ),
			'download' => HtmlTagGeneratorHelper::tag( 'a', __( 'Download' ), array (
				'href' => vsprintf( $linkTemplate . $hrefFilters,
					array ( $_REQUEST['page'], 'downloadSingle', $item['id'] ) )
			) ),
			/*'check'    => HtmlTagGeneratorHelper::tag( 'a', __( 'Check Status' ), array (
				'href' => vsprintf( $linkTemplate, array ( $_REQUEST['page'], 'checkSingle', $item['id'] ) )
			) )*/
		);

		//Return the title contents
		return vsprintf( '%s %s', array ( $item['source_title'], $this->row_actions( $actions ) ) );
	}

	/**
	 * Generates a checkbox for a row to add row to bulk actions
	 *
	 * @param array $item
	 *
	 * @return string
	 */
	public function column_cb ( $item ) {
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
		$columns = $this->manager->getColumnsLabels();

		$columns = array_merge( array ( 'bulkActionCb' => '<input type="checkbox" class="checkall" />' ), $columns );

		return $columns;
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
			'send'     => __( 'Resend' ),
			'download' => __( 'Download' ),
			'check'    => __( 'Check Status' ),
		);

		return $actions;
	}

	/**
	 * Handles actions for multiply objects
	 */
	private function processBulkAction () {
		/**
		 * @var array $submissions
		 */
		$submissions = $this->getFormElementValue( 'submission', array () );

		/**
		 * @var SmartlingCore $ep
		 */
		$ep = Bootstrap::getContainer()->get( 'entrypoint' );

		if ( is_array( $submissions ) ) {
			foreach ( $submissions as $submission ) {
				switch ( $this->current_action() ) {
					case 'download':
						$messages = $ep->downloadTranslationBySubmissionId( $submission );
						break;
					case 'send':
						$messages = $ep->sendForTranslationBySubmissionId( $submission );
						break;
					case 'check':
						$messages = $ep->checkSubmissionById( $submission );
						break;
				}
				if ( isset( $messages ) && is_array( $messages ) ) {
					foreach ( $messages as $message ) {
						DiagnosticsHelper::addDiagnosticsMessage( $message );
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
		if ( $submissionId > 0 ) {
			/**
			 * @var SmartlingCore $ep
			 */
			$ep = Bootstrap::getContainer()->get( 'entrypoint' );

			switch ( $this->current_action() ) {
				case 'downloadSingle':
					$messages = $ep->downloadTranslationBySubmissionId( $submissionId );
					break;
				case 'sendSingle':
					$messages = $ep->sendForTranslationBySubmissionId( $submissionId );
					break;
				case 'checkSingle':
					$messages = $ep->checkSubmissionById( $submissionId );
					break;
			}
			if ( isset( $messages ) && is_array( $messages ) ) {
				foreach ( $messages as $message ) {
					DiagnosticsHelper::addDiagnosticsMessage( $message );
				}
			}
		}
	}

	/**
	 * Handles actions
	 */
	private function processAction () {
		try {
			$this->processBulkAction();
			$this->processSingleAction();
		} catch ( EntityNotFoundException $e ) {
			$msg = 'An error occurred, the database is corrupted. ' . $e->getMessage();
			DiagnosticsHelper::addDiagnosticsMessage( $msg );
		} catch ( Exception $e ) {

			DiagnosticsHelper::addDiagnosticsMessage( $e->getMessage() );
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

		$this->processAction();

		$total = 0;

		$contentTypeFilterValue = $this->getContentTypeFilterValue();

		$statusFilterValue = $this->getStatusFilterValue();

		$searchText = $this->getFromSource( 's', '' );

		if ( empty( $searchText ) ) {
			$data = $this->manager->getEntities( $contentTypeFilterValue, $statusFilterValue,
				$this->getSortingOptions(), $pageOptions,
				$total );
		} else {
			$data = $this->manager->search( $searchText, array ( 'source_title', 'source_id', 'file_uri' ),
				$contentTypeFilterValue, $statusFilterValue, $this->getSortingOptions(), $pageOptions,
				$total );
		}

		$dataAsArray = array ();

		$file_uri_max_chars = 50;

		foreach ( $data as $element ) {
			$row = $element->toArray();

			$row["file_uri"]        = htmlentities( $row["file_uri"] );
			$row['source_title']    = htmlentities( $row['source_title'] );
			$row['source_title']    = $this->applyRowActions( $row );
			$row['content_type']    = WordpressContentTypeHelper::getLocalizedContentType( $row['content_type'] );
			$row['submission_date'] = DateTimeHelper::toWordpressLocalDateTime( DateTimeHelper::stringToDateTime( $row['submission_date'] ) );
			$row['applied_date']    = '0000-00-00 00:00:00' === $row['applied_date'] ? __( 'Never' ) :
				DateTimeHelper::toWordpressLocalDateTime( DateTimeHelper::stringToDateTime( $row['applied_date'] ) );
			$row['target_locale']   = $this->entityHelper->getConnector()->getBlogNameByLocale( $row['target_locale'] );

			if ( mb_strlen( $row['file_uri'], 'utf8' ) > $file_uri_max_chars ) {
				$orig     = $row['file_uri'];
				$shrinked = mb_substr( $orig, 0, $file_uri_max_chars - 3, 'utf8' ) . '...';

				$row['file_uri'] = HtmlTagGeneratorHelper::tag( 'span', $shrinked, array ( 'title' => $orig ) );

			}


			$row = array_merge( array ( 'bulkActionCb' => $this->column_cb( $row ) ), $row );

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

		$types = $this->getActiveContentTypes( $this->entityHelper->getSiteHelper() );

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