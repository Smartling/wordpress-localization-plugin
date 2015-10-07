<?php

namespace Smartling\WP\View;

use DateTime;
use Smartling\Base\SmartlingCore;
use Smartling\Bootstrap;
use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\Helpers\DateTimeHelper;
use Smartling\Helpers\EntityHelper;
use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Helpers\PluginInfo;
use Smartling\Helpers\StringHelper;
use Smartling\Helpers\ThemeSidebarHelper;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Helpers\WordpressUserHelper;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\WP\Controller\SmartlingListTable;


/**
 * Class BulkSubmitTableWidget
 *
 * @package Smartling\WP\View
 */
class BulkSubmitTableWidget extends SmartlingListTable {

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
	 * default values of custom form elements on page
	 *
	 * @var array
	 */
	private $defaultValues = [
		self::CONTENT_TYPE_SELECT_ELEMENT_NAME => WordpressContentTypeHelper::CONTENT_TYPE_POST,
	];

	private $_settings = [
		'singular' => 'submission',
		'plural'   => 'submissions',
		'ajax'     => false,
	];

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
	 * @var ConfigurationProfileEntity
	 */
	private $profile;

	/**
	 * @return ConfigurationProfileEntity
	 */
	public function getProfile () {
		return $this->profile;
	}

	/**
	 * @param SubmissionManager          $manager
	 * @param PluginInfo                 $pluginInfo
	 * @param EntityHelper               $entityHelper
	 * @param ConfigurationProfileEntity $profile
	 */
	public function __construct (
		SubmissionManager $manager,
		PluginInfo $pluginInfo,
		EntityHelper $entityHelper,
		ConfigurationProfileEntity $profile
	) {
		$this->manager      = $manager;
		$this->source       = $_REQUEST;
		$this->pluginInfo   = $pluginInfo;
		$this->entityHelper = $entityHelper;
		$this->profile      = $profile;

		parent::__construct( $this->_settings );
	}

	/**
	 * @return SubmissionManager
	 */
	public function getManager () {
		return $this->manager;
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
		$column    = $this->getFromSource( $fieldNameKey, false );
		$direction = 'ASC';

		if ( false !== $column ) {
			$direction = strtoupper( $this->getFromSource( $orderDirectionKey,
				SmartlingToCMSDatabaseAccessWrapperInterface::SORT_OPTION_ASC ) );
		}

		return [
			'orderby' => $column,
			'order'   => $direction,
		];
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
		$actions = [
			'send' => HtmlTagGeneratorHelper::tag( 'a', __( 'Send' ), [
				'href' => vsprintf( $linkTemplate,
					[ $_REQUEST['page'], 'sendSingle', $item['id'] . '-' . $item['type'] ] ),
			] ),
		];

		//Return the title contents
		return vsprintf( '%s %s', [ $item['title'], $this->row_actions( $actions ) ] );
	}

	/**
	 * Generates a checkbox for a row to add row to bulk actions
	 *
	 * @param array $item
	 *
	 * @return string
	 */
	public function column_cb ( $item ) {

		$t = vsprintf( '%s-%s', [ $item['id'], $item['type'] ] );

		return HtmlTagGeneratorHelper::tag( 'input', '', [
			'type'  => 'checkbox',
			'name'  => $this->buildHtmlTagName( $this->_args['singular'] ) . '[]',
			'value' => $t,
			'id'    => $t,
			'class' => 'bulkaction',
		] );
	}

	/**
	 * @inheritdoc
	 */
	public function get_columns () {
		return [
			'bulkActionCb' => HtmlTagGeneratorHelper::tag(
				'input',
				'',
				[
					'type'  => 'checkbox',
					'class' => 'checkall',
				]
			),
			'id'           => __( 'ID' ),
			'title'        => __( 'Title' ),
			'author'       => __( 'Author' ),
			'status'       => __( 'Status' ),
			'locales'      => __( 'Locales' ),
			'updated'      => __( 'Updated' ),
		];
	}

	/**
	 * @inheritdoc
	 */
	public function get_sortable_columns () {

		$fields = [
			'title',
			'status',
			'author',
			'updated',
		];

		$sortable_columns = [ ];

		foreach ( $fields as $field ) {
			$sortable_columns[ $field ] = [ $field, false ];
		}

		return $sortable_columns;
	}

	/**
	 * @inheritdoc
	 */
	public function get_bulk_actions () {
		return [ ];
	}

	/**
	 * Handles actions for multiply objects
	 */
	private function processBulkAction () {
		/**
		 * @var array $submissions
		 */
		$submissions = $this->getFormElementValue( 'submission', [ ] );
		$locales     = [ ];
		$data        = $this->getFromSource( 'bulk-submit-locales', [ ] );

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
					list( $id, $type ) = explode( '-', $submission );
					$curBlogId = $this->getProfile()->getOriginalBlogId()->getBlogId();

					foreach ( $locales as $blogId => $blogName ) {
						$result = $ep->createForTranslation( $type, $curBlogId, $id, (int) $blogId );
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
		$locales      = [ ];
		$data         = $this->getFromSource( 'bulk-submit-locales', [ ] );
		if ( null !== $data && array_key_exists( 'locales', $data ) ) {
			foreach ( $data['locales'] as $blogId => $blogName ) {
				if ( array_key_exists( 'enabled', $blogName ) && 'on' === $blogName['enabled'] ) {
					$locales[ $blogId ] = $blogName['locale'];
				}
			}

			$type = $this->getFormElementValue( $this->buildHtmlTagName( 'content-type' ), null );

			if ( null === $type ) {
				return;
			}

			if ( $submissionId > 0 && count( $locales ) > 0 ) {
				/**
				 * @var SmartlingCore $ep
				 */
				$ep = Bootstrap::getContainer()->get( 'entrypoint' );

				$curBlogId = $this->getProfile()->getOriginalBlogId()->getBlogId();

				foreach ( $locales as $blogId => $blogName ) {
					$result = $ep->createForTranslation(
						$type,
						$curBlogId,
						$submissionId,
						(int) $blogId,
						$this->getEntityHelper()->getTarget( $submissionId, $blogId )
					);
				}
			}
		}
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
		$pageOptions = [
			'limit' => $this->manager->getPageSize(),
			'page'  => $this->get_pagenum(),
		];

		$this->_column_headers = [
			$this->get_columns(),
			[ 'id' ],
			$this->get_sortable_columns(),
		];
		$this->processAction();

		$contentTypeFilterValue = $this->getContentTypeFilterValue();
		$sortOptions            = $this->getSortingOptions();

		/**
		 * @var SmartlingCore $core
		 */
		$core = Bootstrap::getContainer()->get( 'entrypoint' );
		$io   = $core->getContentIoFactory()->getMapper( $contentTypeFilterValue );


		$data = $io->getAll(
			$pageOptions['limit'],
			( $pageOptions['page'] - 1 ) * $pageOptions['limit'],
			$sortOptions['orderby'],
			$sortOptions['order']
		);

		$total = $io->getTotal();

		$dataAsArray = [ ];
		if ( $data ) {
			foreach ( $data as $item ) {

				$row = $this->extractFields( $item, $contentTypeFilterValue );

				$entities = [ ];

				if ( isset( $row['id'], $row['type'] ) ) {
					$entities = $this->getManager()->find( [
							'source_blog_id' => $this->getEntityHelper()->getSiteHelper()->getCurrentBlogId(),
							'source_id'      => $row['id'],
							'content_type'   => $row['type'],
						]
					);
				} else {
					continue;
				}

				if ( count( $entities ) > 0 ) {
					$locales = [ ];
					foreach ( $entities as $entity ) {
						$locales[] =
							$this->entityHelper->getConnector()->getBlogNameByLocale( $entity->getTargetLocale() );
					}

					$row['locales'] = implode( ', ', $locales );
				}

				$file_uri_max_chars = 50;
				if ( mb_strlen( $row['title'], 'utf8' ) > $file_uri_max_chars ) {
					$orig     = $row['title'];
					$shrinked = mb_substr( $orig, 0, $file_uri_max_chars - 3, 'utf8' ) . '...';

					$row['title'] = HtmlTagGeneratorHelper::tag( 'span', $shrinked, [ 'title' => $orig ] );
				}

				//$row['title']  = $this->applyRowActions( $row );

				$updatedDate = '';
				if ( ! StringHelper::isNullOrEmpty( $row['updated'] ) ) {
					$dt = DateTimeHelper::stringToDateTime( $row['updated'] );
					if ( $dt instanceof DateTime ) {
						$updatedDate = DateTimeHelper::toWordpressLocalDateTime( $dt );
					}
				}

				$row['updated'] = $updatedDate;
				$row            = array_merge( [ 'bulkActionCb' => $this->column_cb( $row ) ], $row );
				$dataAsArray[]  = $row;
			}
		}


		$this->items = $dataAsArray;

		$this->set_pagination_args( [
			'total_items' => $total,
			'per_page'    => $pageOptions['limit'],
			'total_pages' => ceil( $total / $pageOptions['limit'] ),
		] );
	}

	/**
	 * @param $item
	 * @param $type
	 *
	 * @return array
	 */
	private function extractFields ( $item, $type ) {
		switch ( $type ) {
			case WordpressContentTypeHelper::CONTENT_TYPE_POST:
			case WordpressContentTypeHelper::CONTENT_TYPE_PAGE:
			case WordpressContentTypeHelper::CONTENT_TYPE_POST_POLICY:
			case WordpressContentTypeHelper::CONTENT_TYPE_POST_PARTNER:
			case WordpressContentTypeHelper::CONTENT_TYPE_POST_TESTIMONIAL:
				return [
					'id'      => $item->ID,
					'title'   => $item->post_title,
					'type'    => $item->post_type,
					'author'  => WordpressUserHelper::getUserLoginById( (int) $item->post_author ),
					'status'  => $item->post_status,
					'locales' => null,
					'updated' => $item->post_date,
				];
				break;
			case WordpressContentTypeHelper::CONTENT_TYPE_POST_TAG:
			case WordpressContentTypeHelper::CONTENT_TYPE_CATEGORY:
			case WordpressContentTypeHelper::CONTENT_TYPE_NAV_MENU:
				return [
					'id'      => $item->term_id,
					'title'   => $item->name,
					'type'    => $item->taxonomy,
					'author'  => null,
					'status'  => null,
					'locales' => null,
					'updated' => null,
				];
				break;
			case WordpressContentTypeHelper::CONTENT_TYPE_WIDGET:
				return [
					'id'      => $item->getId(),
					'title'   => '"' . $item->getTitle() . '" on ' . ThemeSidebarHelper::getSideBarLabel($item->getBar()) . '(position ' . $item->getBarPosition() . ')',
					'type'    => $item->getType(),
					'author'  => $item->getIndex(),
					'status'  => null,
					'locales' => null,
					'updated' => null,
				];
				break;
		}

		return [ ];
	}

	/**
	 * @return string
	 */
	public function contentTypeSelectRender () {
		$controlName = 'content-type';

		$types = $this->getActiveContentTypes( $this->entityHelper->getSiteHelper() );

		$restrictedTypes = WordpressContentTypeHelper::getTypesRestrictedToBulkSubmit();

		$typesFiltered = [ ];

		foreach ( $types as $value => $title ) {
			if ( in_array( $value, $restrictedTypes ) ) {
				continue;
			}
			$typesFiltered[ $value ] = $title;
		}

		$value = $this->getFormElementValue(
			$controlName,
			$this->defaultValues[ $controlName ]
		);

		$html = HtmlTagGeneratorHelper::tag(
				'label',
				__( 'Type' ),
				[
					'for' => $this->buildHtmlTagName( $controlName ),
				]
			) . HtmlTagGeneratorHelper::tag(
				'select',
				HtmlTagGeneratorHelper::renderSelectOptions(
					$value,
					$typesFiltered
				),
				[
					'id'   => $this->buildHtmlTagName( $controlName ),
					'name' => $this->buildHtmlTagName( $controlName ),
				]
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

		$options = [
			'type'  => 'submit',
			'id'    => $id,
			//'name'  => $name,
			'class' => 'button action',
			'value' => __( $label ),

		];

		return HtmlTagGeneratorHelper::tag( 'input', '', $options );
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