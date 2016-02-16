<?php
namespace Smartling\Tests\Entities;

use Smartling\Bootstrap;
use Smartling\DbAl\WordpressContentEntities\PostEntity;
use Smartling\Exception\SmartlingInvalidFactoryArgumentException;
use Smartling\Helpers\DateTimeHelper;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Processors\ContentEntitiesIOFactory;
use Smartling\Tests\Mocks\WordpressFunctionsMockHelper;

/**
 * Class PostTest
 */
class PostTest extends \PHPUnit_Framework_TestCase
{

	/**
	 * @var ContentEntitiesIOFactory
	 */
	private $ioFactory;

	public function __construct($name = null, array $data = [], $dataName = '')
	{
		WordpressFunctionsMockHelper::injectFunctionsMocks();

		parent::__construct($name, $data, $dataName);

		$this->ioFactory = Bootstrap::getContainer()->get('factory.contentIO');
	}

	public function testGetPostWrapper()
	{
		$type = WordpressContentTypeHelper::CONTENT_TYPE_POST;

		$wrapper = $this->ioFactory->getMapper($type);

		self::assertTrue($wrapper instanceof PostEntity);
	}

	public function testGetPostWrapperException()
	{
		$type = WordpressContentTypeHelper::CONTENT_TYPE_POST;

		$type = strrev($type);
		try {
			$wrapper = $this->ioFactory->getMapper($type);
		} catch (SmartlingInvalidFactoryArgumentException $e) {
			self::assertTrue($e instanceof SmartlingInvalidFactoryArgumentException);
		}
	}

	public function testReadPost()
	{
		$type = WordpressContentTypeHelper::CONTENT_TYPE_POST;

		$wrapper = $this->ioFactory->getMapper($type);

		$result = $wrapper->get(1);


		self::assertTrue($result instanceof PostEntity);

		self::assertTrue($result->ID === 1);

		self::assertTrue($result->post_title === 'Here goes the title');

		self::assertTrue($result->guid === '/here-goes-the-title');

		self::assertTrue($result->post_type === $type);
	}

	public function testClonePost()
	{
		$type = WordpressContentTypeHelper::CONTENT_TYPE_POST;

		$wrapper = $this->ioFactory->getMapper($type);

		$result = $wrapper->get(1);

		$clone = clone $result;

		$originalClass = get_class($result);

		self::assertTrue($clone instanceof $originalClass);

		self::assertTrue($clone !== $result);

	}

	public function testCleanPostFields()
	{
		$type = WordpressContentTypeHelper::CONTENT_TYPE_POST;

		$wrapper = $this->ioFactory->getMapper($type);

		$result = $wrapper->get(1);

		$clone = clone $result;

		$clone->cleanFields();

		self::assertTrue(null === $clone->ID);
	}

	public function testCreatePost()
	{
		$type = WordpressContentTypeHelper::CONTENT_TYPE_POST;

		$wrapper = $this->ioFactory->getMapper($type);
		$result = $wrapper->get(1);
		$clone = clone $result;
		$clone->cleanFields();
		$clone->post_title = 'test';
		$clone->post_content = 'test';
		$id = $wrapper->set($clone);

		self::assertTrue(2 === $id);
	}

	public function testUpdatePost()
	{
		$type = WordpressContentTypeHelper::CONTENT_TYPE_POST;

		$wrapper = $this->ioFactory->getMapper($type);
		$result = $wrapper->get(1);
		$result->post_title .= 'new';
		$id = $wrapper->set($result);

		self::assertTrue(1 === $id);
	}
}