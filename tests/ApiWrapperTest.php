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
	public function __construct ( $name = null, array $data = [ ], $dataName = '' ) {
		parent::__construct( $name, $data, $dataName );

		$this->container = Bootstrap::getContainer();

		$bs = new Bootstrap();
		$bs->load();

		$this->container->set( 'multilang.proxy', $this->getTranslationProxyMock() );

		$this->container->set( 'site.db', $this->getMockDatabaseAL() );

		$this->prepareSettingsManager();
		$this->ep = $this->container->get( 'entrypoint' );
	}

	private function prepareSettingsManager () {
		$this->prepareMockProfile();

		$this->container->set(
			'wrapper.sdk.api.smartling',
			new ApiWrapperMock(
				$this->container->get( 'manager.settings' ),
				$this->container->get( 'logger' )
			)
		);
	}

	/**
	 * @return PHPUnit_Framework_MockObject_MockObject
	 */
	private function getMockDatabaseAL () {
		$dbalMock = $this
			->getMockBuilder( 'Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapper' )
			->setMethods(
				[
					'query',
					'completeTableName',
					'getLastInsertedId',
					'fetch',
					'escape',
					'__construct',
					'needRawSqlLog',
				]
			)
			->setConstructorArgs(
				[
					$this->container->get( 'logger' ),
				]
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
				[
					'getLocales',
					'getBlogLocaleById',
					'getLinkedBlogIdsByBlogId',
					'getLinkedObjects',
					'linkObjects',
					'unlinkObjects',
					'getBlogLanguageById',
				]
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

		$fields = [
			'id'                     => null,
			'source_title'           => 'Automatic generated title',
			'source_blog_id'         => 1,
			'source_content_hash'    => md5( '' ),
			'content_type'           => WordpressContentTypeHelper::CONTENT_TYPE_POST,
			'source_id'              => '/ol"olo',
			'file_uri'               => '/tralala\'',
			'target_locale'          => 'es_US',
			'target_blog_id'         => 2,
			'target_id'              => '',
			'submitter'              => 'admin',
			'submission_date'        => time(),
			'approved_string_count'  => 37,
			'completed_string_count' => 14,
			'status'                 => 'New',
		];

		return $manager->createSubmission( $fields );
	}

	public function testCheckStatus () {
		$entity = $this->getSubmissionEntity();

		$msg = $this->ep->checkSubmissionByEntity( $entity );

		self::assertTrue( 0 === count( $msg ),
			vsprintf( 'Expected no error messages. Got: ', [ implode( ',', $msg ) ] ) );

		self::assertTrue( 100 === $entity->getCompletionPercentage() );
	}

	public function testUpload () {
		$entity = $this->getSubmissionEntity();
		$entity->setId( 1 );

		$this->prepareMockProfile();

		$result = $this->ep->sendForTranslationBySubmission( $entity );

		self::assertTrue( $result );
	}

	public function testDownload () {
		$entity = $this->getSubmissionEntity();

		$this->container->get( 'multilang.proxy' )
		                ->expects( $this->any() )
		                ->method( 'linkObjects' )
		                ->willReturn( true );

		$msg = $this->ep->downloadTranslationBySubmission( $entity );

		self::assertTrue( 0 === count( $msg ), var_export( $msg, true ) );
	}


	/**
	 * @param string   $profileName
	 * @param int      $isActive
	 * @param int      $originalBlogId
	 * @param int      $autoAuthorize
	 * @param int|null $id
	 *
	 * @return array
	 */
	private function getProfileDataStructure (
		$profileName = 'Mock Profile',
		$isActive = 1,
		$originalBlogId = 1,
		$autoAuthorize = 1,
		$id = null
	) {
		return [
			'id'               => $id ? : rand( 0, PHP_INT_MAX ),
			'profile_name'     => $profileName,
			'api_url'          => 'httpq://mock.api.url/v0',
			'project_id'       => '123456789',
			'api_key'          => 'de305d54-75b4-431b-adb2-eb6b9e546014', // wrom wiki
			'is_active'        => (int) $isActive,
			'original_blog_id' => (int) $originalBlogId,
			'auto_authorize'   => (int) $autoAuthorize,
			'retrieval_type'   => 'pseudo',
			'target_locales'   => json_encode( [
				(object) [
					'smartlingLocale' => 'es-ES',
					'enabled'         => true,
					'blogId'          => 2,
				],
				(object) [
					'smartlingLocale' => 'fr-FR',
					'enabled'         => true,
					'blogId'          => 3,
				],
				(object) [
					'smartlingLocale' => 'fr-FR',
					'enabled'         => false,
					'blogId'          => 4,
				],
			] ),
		];
	}

	private function prepareMockProfile () {
		/**
		 * @var PHPUnit_Framework_MockObject_MockObject $mock
		 */
		$mock   = $this->container->get( 'site.db' );
		$struct = [ (object) $this->getProfileDataStructure() ];
		$mock->expects( self::any() )->method( 'completeTableName' )->willReturn( 'wp_mock_table_name' );
		$mock->expects( self::any() )->method( 'fetch' )->willReturn( $struct );
	}


}

