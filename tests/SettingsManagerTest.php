<?php

use Psr\Log\LoggerInterface;
use Smartling\Bootstrap;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;
use Smartling\Settings\TargetLocale;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class SettingsManagerTest extends PHPUnit_Framework_TestCase {


	/**
	 * @var ContainerBuilder
	 */
	private $container;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @inheritdoc
	 */
	public function __construct ( $name = null, array $data = array (), $dataName = '' ) {
		parent::__construct( $name, $data, $dataName );

		$this->container = Bootstrap::getContainer();

		$this->logger = $this->container->get( 'logger' );

		$bs = new Bootstrap();
		$bs->load();
	}

	/**
	 * @return PHPUnit_Framework_MockObject_MockObject
	 */
	private function setupDbAlMock () {
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

	/**
	 * @param string $id
	 */
	private function replaceDbAlInContainer ( $id = 'site.db' ) {
		$dbalMock = $this->setupDbAlMock();

		$this->container->set( 'site.db', $dbalMock );
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
		return array (
			'id'               => $id ? : rand( 0, PHP_INT_MAX ),
			'profile_name'     => $profileName,
			'api_url'          => 'httpq://mock.api.url/v0',
			'project_id'       => '123456789',
			'api_key'          => 'de305d54-75b4-431b-adb2-eb6b9e546014', // wrom wiki
			'is_active'        => (int) $isActive,
			'original_blog_id' => (int) $originalBlogId,
			'auto_authorize'   => (int) $autoAuthorize,
			'retrieval_type'   => 'pseudo',
			'target_locales'   => json_encode( array (
				(object) array (
					'smartlingLocale' => 'es-ES',
					'enabled'         => true,
					'blogId'          => 2
				),
				(object) array (
					'smartlingLocale' => 'fr-FR',
					'enabled'         => true,
					'blogId'          => 3
				),
				(object) array (
					'smartlingLocale' => 'fr-FR',
					'enabled'         => false,
					'blogId'          => 4
				),
			) ),
		);
	}

	/**
	 * @param string   $profileName
	 * @param int      $isActive
	 * @param int      $originalBlogId
	 * @param int      $autoAuthorize
	 * @param int|null $id
	 */
	private function prepareMockProfile (
		$profileName = 'Mock Profile',
		$isActive = 1,
		$originalBlogId = 1,
		$autoAuthorize = 1,
		$id = null
	) {
		/**
		 * @var PHPUnit_Framework_MockObject_MockObject $mock
		 */
		$mock = $this->container->get( 'site.db' );

		$mock->expects( self::at( 0 ) )->method( 'completeTableName' )->willReturn( 'wp_mock_table_name' );
		$mock->expects( self::at( 1 ) )->method( 'fetch' )->willReturn(
			array (
				(object) $this->getProfileDataStructure( $profileName, $isActive, $originalBlogId, $autoAuthorize, $id )
			) );
	}

	/**
	 * @param int $count
	 */
	private function prepareSeveralProfiles ( $count = 2 ) {
		$set = array ();

		for ( $i = 0; $i < (int) $count; $i ++ ) {
			$set[] = (object) $this->getProfileDataStructure();
		}

		/**
		 * @var PHPUnit_Framework_MockObject_MockObject $mock
		 */
		$mock = $this->container->get( 'site.db' );

		$mock->expects( self::at( 0 ) )->method( 'completeTableName' )->willReturn( 'wp_mock_table_name' );
		$mock->expects( self::at( 1 ) )->method( 'fetch' )->willReturn( $set );
	}

	public function testReadOneConfigurationProfileFromDatabase () {
		$this->replaceDbAlInContainer();
		$this->prepareMockProfile();

		/**
		 * @var SettingsManager $manager
		 */
		$manager = $this->container->get( 'manager.settings' );

		$entity = $manager->findEntityByMainLocale( 1 );

		self::assertTrue( 1 === count( $entity ) );
	}

	public function testReadConfigurationProfileByOriginalBlogIdFromDatabase () {
		$this->replaceDbAlInContainer();
		$this->prepareMockProfile();

		/**
		 * @var SettingsManager $manager
		 */
		$manager = $this->container->get( 'manager.settings' );

		$entity = $manager->findEntityByMainLocale( 1 );

		$profile = reset( $entity );
		/**
		 * @var ConfigurationProfileEntity $profile
		 */

		self::assertTrue( 1 === $profile->getOriginalBlogId()->getBlogId() );
	}

	public function testReadTwoConfigurationProfilesByOriginalBlogIdFromDatabase () {
		$this->replaceDbAlInContainer();
		$this->prepareSeveralProfiles();

		/**
		 * @var SettingsManager $manager
		 */
		$manager = $this->container->get( 'manager.settings' );

		$entity = $manager->findEntityByMainLocale( 1 );

		self::assertTrue( 2 === count( $entity ) );
	}

	public function testConfigurationProfileProperties () {
		$this->replaceDbAlInContainer();
		$this->prepareMockProfile( 'test profile' );

		/**
		 * @var SettingsManager $manager
		 */
		$manager = $this->container->get( 'manager.settings' );

		$entity = $manager->findEntityByMainLocale( 1 );

		$profile = reset( $entity );
		/**
		 * @var ConfigurationProfileEntity $profile
		 */

		self::assertTrue( 1 === $profile->getIsActive() );
		self::assertTrue( true === $profile->getAutoAuthorize() );
		self::assertTrue( 'test profile' === $profile->getProfileName() );

	}

	public function testConfigurationProfileTargetProfilesProperty () {
		$this->replaceDbAlInContainer();
		$this->prepareMockProfile();

		/**
		 * @var SettingsManager $manager
		 */
		$manager = $this->container->get( 'manager.settings' );

		$entity = $manager->findEntityByMainLocale( 1 );

		$profile = reset( $entity );
		/**
		 * @var ConfigurationProfileEntity $profile
		 */
		$targetLocales = $profile->getTargetLocales();
		self::assertTrue( is_array( $targetLocales ) );
		$targetLocale = reset( $targetLocales );
		self::assertTrue( $targetLocale instanceof TargetLocale );
	}

	public function testConfigurationProfileTargetProfile () {
		$this->replaceDbAlInContainer();
		$this->prepareMockProfile();

		/**
		 * @var SettingsManager $manager
		 */
		$manager = $this->container->get( 'manager.settings' );

		$entity = $manager->findEntityByMainLocale( 1 );

		$profile = reset( $entity );
		/**
		 * @var ConfigurationProfileEntity $profile
		 */
		$targetLocales = $profile->getTargetLocales();
		/**
		 * @var TargetLocale $targetLocale
		 */
		$targetLocale = reset( $targetLocales );

		self::assertTrue( 2 === $targetLocale->getBlogId() );
		self::assertTrue( true === $targetLocale->isEnabled() );
		self::assertTrue( 'es-ES' === $targetLocale->getSmartlingLocale() );
	}

	public function testSaveConfigurationProfile () {
		$this->replaceDbAlInContainer();
		$this->prepareMockProfile();

		/**
		 * @var SettingsManager $manager
		 */
		$manager = $this->container->get( 'manager.settings' );

		$entity = $manager->findEntityByMainLocale( 1 );

		$profile = reset( $entity );

		/**
		 * @var ConfigurationProfileEntity $profile
		 */

		$profile->setId( 0 ); // force re-insert

		/**
		 * @var PHPUnit_Framework_MockObject_MockObject $mock
		 */
		$mock = $this->container->get( 'site.db' );

		$mock->expects( self::at( 0 ) )->method( 'completeTableName' )->willReturn( 'wp_mock_table_name' );
		$mock->expects( self::at( 1 ) )->method( 'query' )->willReturn( true );
		$mock->expects( self::at( 2 ) )->method( 'getLastInsertedId' )->willReturn( 88 );

		$newEntity = $manager->storeEntity( $profile );

		$new_id = $newEntity->getId();

		self::assertTrue( $new_id === 88 );
	}
}