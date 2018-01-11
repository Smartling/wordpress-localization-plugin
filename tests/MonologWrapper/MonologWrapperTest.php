<?php

namespace Smartling\Tests;

use PHPUnit\Framework\TestCase;
use Smartling\MonologWrapper\MonologWrapper;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Class MonologWrapperTest
 *
 * @package Tests
 */
class MonologWrapperTest extends TestCase {

  /**
   * @var YamlFileLoader
   */
  private $loader;

  /**
   * @var ContainerBuilder
   */
  private $container;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    $this->container = new ContainerBuilder();
    $this->loader = new YamlFileLoader($this->container, new FileLocator(__DIR__));
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown() {
    MonologWrapper::clear();
  }

  /**
   * Get logger before MonologWrapper initialization.
   */
  public function testGetLoggerBeforeInit() {
    $this->loader->load(__DIR__ . '/resources/MonologWrapperTests/services_default_logger.yml');

    $result1 = $this->container->get('service.example.1')->doSomething();

    $this->assertEquals('Smartling\MonologWrapper\Logger\DevNullLogger', get_class($result1['logger']));
  }

  /**
   * No defined loggers: test MonologWrapper::getLogger method without
   * parameter.
   */
  public function testNoDefinedLoggersGetLoggerWithoutParameter() {
    $this->loader->load(__DIR__ . '/resources/MonologWrapperTests/services_without_logger.yml');
    MonologWrapper::init($this->container);

    $result1 = $this->container->get('service.example.4')->doSomething();

    $this->assertEquals('Smartling\MonologWrapper\Logger\DevNullLogger', get_class($result1['logger']));
  }

  /**
   * Default defined logger: test MonologWrapper::getLogger method without
   * parameter.
   */
  public function testDefaultDefinedLoggerGetLoggerWithoutParameter() {
    $this->loader->load(__DIR__ . '/resources/MonologWrapperTests/services_default_logger.yml');
    MonologWrapper::init($this->container);

    $result1 = $this->container->get('service.example.4')->doSomething();

    $this->assertEquals('Smartling\MonologWrapper\Logger\LevelLogger', get_class($result1['logger']));
    $this->assertEquals('default', $result1['logger']->getName());
  }

  /**
   * No defined loggers: DevNullLogger must be returned.
   */
  public function testNoDefinedLoggers() {
    $this->loader->load(__DIR__ . '/resources/MonologWrapperTests/services_without_logger.yml');
    MonologWrapper::init($this->container);

    $result1 = $this->container->get('service.example.1')->doSomething();

    $this->assertEquals('Smartling\MonologWrapper\Logger\DevNullLogger', get_class($result1['logger']));
  }

  /**
   * Default logger: LevelLogger must be returned with 'default' name.
   */
  public function testDefaultLogger() {
    $this->loader->load(__DIR__ . '/resources/MonologWrapperTests/services_default_logger.yml');
    MonologWrapper::init($this->container);

    $result1 = $this->container->get('service.example.1')->doSomething();
    $result2 = $this->container->get('service.example.2')->doSomething();
    $result3 = $this->container->get('service.example.3')->doSomething();

    $this->assertEquals('Smartling\MonologWrapper\Logger\LevelLogger', get_class($result1['logger']));
    $this->assertEquals('Smartling\MonologWrapper\Logger\LevelLogger', get_class($result2['logger']));
    $this->assertEquals('Smartling\MonologWrapper\Logger\LevelLogger', get_class($result3['logger']));

    $this->assertEquals('default', $result1['logger']->getName());
    $this->assertEquals('default', $result2['logger']->getName());
    $this->assertEquals('default', $result3['logger']->getName());
  }

  /**
   * Namespace logger: default LevelLogger must be returned with 'Tests' name.
   */
  public function testNamespaceLogger() {
    $this->loader->load(__DIR__ . '/resources/MonologWrapperTests/services_namespace_logger.yml');
    MonologWrapper::init($this->container);

    $result1 = $this->container->get('service.example.1')->doSomething();
    $result2 = $this->container->get('service.example.2')->doSomething();
    $result3 = $this->container->get('service.example.3')->doSomething();

    $this->assertEquals('Smartling\MonologWrapper\Logger\LevelLogger', get_class($result1['logger']));
    $this->assertEquals('Smartling\MonologWrapper\Logger\LevelLogger', get_class($result2['logger']));
    $this->assertEquals('Smartling\MonologWrapper\Logger\LevelLogger', get_class($result3['logger']));

    $this->assertEquals('Smartling\Tests', $result1['logger']->getName());
    $this->assertEquals('Smartling\Tests', $result2['logger']->getName());
    $this->assertEquals('Smartling\Tests', $result3['logger']->getName());
  }

  /**
   * Deep namespace logger: default LevelLogger must be returned.
   * Example1 and Example2 - 'Tests' logger.
   * Example4 - 'Tests\Deep' logger.
   */
  public function testDeepNamespaceLoggers() {
    $this->loader->load(__DIR__ . '/resources/MonologWrapperTests/services_deep_namespace_logger.yml');
    MonologWrapper::init($this->container);

    $result1 = $this->container->get('service.example.1')->doSomething();
    $result2 = $this->container->get('service.example.2')->doSomething();
    $result3 = $this->container->get('service.example.3')->doSomething();

    $this->assertEquals('Smartling\MonologWrapper\Logger\LevelLogger', get_class($result1['logger']));
    $this->assertEquals('Smartling\MonologWrapper\Logger\LevelLogger', get_class($result2['logger']));
    $this->assertEquals('Smartling\MonologWrapper\Logger\LevelLogger', get_class($result3['logger']));

    $this->assertEquals('Smartling\Tests', $result1['logger']->getName());
    $this->assertEquals('Smartling\Tests', $result2['logger']->getName());
    $this->assertEquals('Smartling\Tests\MonologWrapper\Examples\Deep', $result3['logger']->getName());
  }

  /**
   * Namespace and class loggers: default LevelLogger must be returned.
   * Example1 - 'Tests' logger.
   * Example2 - 'Tests' logger.
   * Example3 - 'Tests' logger.
   * Example4 - 'Tests\Deep' logger.
   */
  public function testNamespaceAndClassLoggers() {
    $this->loader->load(__DIR__ . '/resources/MonologWrapperTests/services_namespace_and_class_logger.yml');
    MonologWrapper::init($this->container);

    $result1 = $this->container->get('service.example.1')->doSomething();
    $result2 = $this->container->get('service.example.2')->doSomething();
    $result3 = $this->container->get('service.example.3')->doSomething();
    $result4 = $this->container->get('service.example.4')->doSomething(true);

    $this->assertEquals('Smartling\MonologWrapper\Logger\LevelLogger', get_class($result1['logger']));
    $this->assertEquals('Smartling\MonologWrapper\Logger\LevelLogger', get_class($result2['logger']));
    $this->assertEquals('Smartling\MonologWrapper\Logger\LevelLogger', get_class($result3['logger']));
    $this->assertEquals('Smartling\MonologWrapper\Logger\LevelLogger', get_class($result4['logger']));

    $this->assertEquals('Smartling\Tests', $result1['logger']->getName());
    $this->assertEquals('Smartling\Tests', $result2['logger']->getName());
    $this->assertEquals('Smartling\Tests\MonologWrapper\Examples\Deep', $result3['logger']->getName());
    $this->assertEquals('Smartling\Tests\MonologWrapper\Examples\Deep\Example4', $result4['logger']->getName());
  }

}
