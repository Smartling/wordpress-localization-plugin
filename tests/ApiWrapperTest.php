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
					'getBlogLanguageById'
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
			'source_title'          => 'Automatic generated title',
			'source_blog_id'           => 1,
			'source_content_hash'    => md5( '' ),
			'content_type'          => WordpressContentTypeHelper::CONTENT_TYPE_POST,
			'source_id'           => '/ol"olo',
			'file_uri'              => "/tralala'",
			'target_locale'         => 'es_US',
			'target_blog_id'           => 2,
			'target_id'           => '',
			'submitter'            => 'admin',
			'submission_date'       => time(),
			'approved_string_count'  => 37,
			'completed_string_count' => 14,
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

		self::assertTrue( 0 === count( $msg ), var_export( $msg, true ) );
	}
}

