<?php

use Smartling\ApiWrapperMock;
use Smartling\Base\SmartlingCore;
use Smartling\Bootstrap;
use Smartling\Helpers\DateTimeHelper;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class ApiWrapperTest
 */
class ApiWrapperTest extends PHPUnit_Framework_TestCase {

	/**
	 * @var ContainerBuilder
	 */
	private $container;

	/**
	 * @var SmartlingCore
	 */
	private $ep;

	/**
	 * @inheritdoc
	 */
	public function __construct ( $name = null, array $data = array (), $dataName = '' ) {
		parent::__construct( $name, $data, $dataName );

		$this->container = Bootstrap::getContainer();

		$bs = new Bootstrap();
		$bs->load();

		$this->mockWordpressApiFunctions();

		$this->container->set( 'multilang.proxy', $this->getTranslationProxyMock() );

		$this->container->set(
			'wrapper.sdk.api.smartling',
			new ApiWrapperMock(
				$this->container->get( 'manager.settings' ),
				$this->container->get( 'logger' )
			)
		);

		$this->container->set( 'site.db', $this->getMockDatabaseAL() );

		$this->ep = $this->container->get( 'entrypoint' );
	}

	/**
	 * emulates wordpress API functions:
	 * - __()
	 * - get_site_option()
	 * - get_current_blog_id()
	 * - get_current_site()
	 * - wp_get_current_user()
	 * - wp_get_sites()
	 * - is_wp_error()
	 * - get_post()
	 * - ms_is_switched()
	 * - restore_current_blog()
	 * - switch_to_blog()
	 * - wp_insert_post()
	 *
	 * emulates global constants:
	 * - ARRAY_A
	 */
	private function mockWordpressApiFunctions () {

		defined( 'ARRAY_A' ) || define( 'ARRAY_A', 'ARRAY_A' );

		if ( ! function_exists( '__' ) ) {
			function __ ( $text, $scope = '' ) {
				return $text;
			}
		}

		if ( ! function_exists( 'get_current_blog_id' ) ) {
			function get_current_blog_id () {
				return 1;
			}
		}

		if ( ! function_exists( 'get_current_site' ) ) {
			function get_current_site () {
				return (object) array ( 'id' => 1 );
			}
		}

		if ( ! function_exists( 'wp_get_current_user' ) ) {
			function wp_get_current_user () {
				return (object) array ( 'user_login' => 1 );
			}
		}

		if ( ! function_exists( 'wp_get_sites' ) ) {
			function wp_get_sites () {
				return array (
					array (
						'site_id' => 1,
						'blog_id' => 1
					),
					array (
						'site_id' => 1,
						'blog_id' => 2
					),
				);
			}
		}

		if ( ! function_exists( 'ms_is_switched' ) ) {
			function ms_is_switched () {
				return true;
			}
		}

		if ( ! function_exists( 'restore_current_blog' ) ) {
			function restore_current_blog () {
				return true;
			}
		}

		if ( ! function_exists( 'switch_to_blog' ) ) {
			function switch_to_blog ( $blogId ) {
				return true;
			}
		}

		if ( ! function_exists( 'get_site_option' ) ) {
			function get_site_option ( $key, $default = null, $useCache = true ) {
				switch ( $key ) {
					case SettingsManager::SMARTLING_ACCOUNT_INFO: {
						return array (
							'apiUrl'        => 'https://capi.smartling.com/v1',
							'projectId'     => 'a',
							'key'           => 'b',
							'retrievalType' => 'pseudo',
							'callBackUrl'   => '',
							'autoAuthorize' => true
						);
						break;
					}
					case SettingsManager::SMARTLING_LOCALES: {
						return array (
							'defaultLocale' => 'en-US',
							'targetLocales' => array (
								array (
									'locale'  => 'ru-Ru',
									'target'  => true,
									'enabled' => true,
									'blog'    => 2
								)
							),
							'defaultBlog'   => 1

						);
						break;
					}
				}

			}
		}

		if ( ! function_exists( 'is_wp_error' ) ) {
			function is_wp_error ( $something ) {
				return false;
			}
		}

		if ( ! function_exists( 'get_post' ) ) {
			function get_post ( $id, $returnError ) {

				$date = DateTimeHelper::nowAsString();

				$post = array (
					'ID'                    => 1,
					'post_author'           => 1,
					'post_date'             => $date,
					'post_date_gmt'         => $date,
					'post_content'          => 'Test content',
					'post_title'            => 'Here goes the title',
					'post_excerpt'          => '',
					'post_status'           => 'published',
					'comment_status'        => 'open',
					'ping_status'           => '',
					'post_password'         => '',
					'post_name'             => 'Here goes the title',
					'to_ping'               => '',
					'pinged'                => '',
					'post_modified'         => $date,
					'post_modified_gmt'     => $date,
					'post_content_filtered' => '',
					'post_parent'           => 0,
					'guid'                  => '/here-goes-the-title',
					'menu_order'            => 0,
					'post_type'             => 'post',
					'post_mime_type'        => 'post',
					'comment_count'         => 0,
				);

				return $post;
			}
		}

		if ( ! function_exists( 'wp_insert_post' ) ) {
			function wp_insert_post ( array $fields, $returnError ) {
				return 2;
			}
		}


	}


	/**
	 * @return PHPUnit_Framework_MockObject_MockObject
	 */
	private function getMockDatabaseAL () {
		$dbalMock = $this
			->getMockBuilder( 'Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapper' )
			->setMethods(
				array (
					'query',
					'completeTableName',
					'getLastInsertedId',
					'fetch',
					'escape',
					'__construct'
				)
			)
			->setConstructorArgs(
				array (
					$this->container->get( 'logger' )
				)
			)
			->setMockClassName( 'MockDb' )
			->disableProxyingToOriginalMethods()
			->getMock();

		return $dbalMock;
	}

	private function getTranslationProxyMock () {
		$translationMock = $this
			->getMockBuilder( 'Smartling\DbAl\MultiligualPressProConnector' )
			->setMethods(
				array (
					'getLocales',
					'getBlogLocaleById',
					'getLinkedBlogIdsByBlogId',
					'getLinkedObjects',
					'linkObjects',
					'unlinkObjects',
				)
			)
			->disableOriginalConstructor()
			->setMockClassName( 'TranslationProxyMock' )
			->disableProxyingToOriginalMethods()
			->getMock();

		return $translationMock;
	}


	/**
	 * @return SubmissionEntity
	 */
	private function getSubmissionEntity () {
		/**
		 * @var SubmissionManager $manager
		 */
		$manager = $this->container->get( 'manager.submission' );

		$fields = array (
			'id'                   => null,
			'sourceTitle'          => 'Automatic generated title',
			'sourceBlog'           => 1,
			'sourceContentHash'    => md5( '' ),
			'contentType'          => WordpressContentTypeHelper::CONTENT_TYPE_POST,
			'sourceGUID'           => '/ol"olo',
			'fileUri'              => "/tralala'",
			'targetLocale'         => 'es_US',
			'targetBlog'           => 2,
			'targetGUID'           => '',
			'submitter'            => 'admin',
			'submissionDate'       => time(),
			'approvedStringCount'  => 37,
			'completedStringCount' => 14,
			'status'               => 'New',
		);

		return $manager->createSubmission( $fields );
	}

	public function testCheckStatus () {
		$entity = $this->getSubmissionEntity();

		$msg = $this->ep->checkSubmissionByEntity( $entity );

		self::assertTrue( 0 === count( $msg ) && 100 === $entity->getCompletionPercentage() );
	}

	public function testUpload () {
		$entity = $this->getSubmissionEntity();

		$result = $this->ep->sendForTranslationBySubmission( $entity );

		self::assertTrue( $result );
	}

	public function testDownload () {
		$entity = $this->getSubmissionEntity();

		$this->container->get( 'multilang.proxy' )
			->expects( $this->once() )
			->method( 'linkObjects' )
			->willReturn( true );

		$msg = $this->ep->downloadTranslationBySubmission( $entity );

		self::assertTrue( 0 === count( $msg ) );
	}
}

