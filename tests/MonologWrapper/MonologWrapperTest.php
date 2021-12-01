<?php

namespace Smartling\Tests\MonologWrapper;

use PHPUnit\Framework\TestCase;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Vendor\Symfony\Component\Config\FileLocator;
use Smartling\Vendor\Symfony\Component\DependencyInjection\ContainerBuilder;
use Smartling\Vendor\Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Smartling\MonologWrapper\Logger\DevNullLogger;
use Smartling\MonologWrapper\Logger\LevelLogger;
use Smartling\Tests\MonologWrapper\Examples\Example1;
use Smartling\Tests\MonologWrapper\Examples\Example2;
use Smartling\Tests\MonologWrapper\Examples\Deep\Example3;
use Smartling\Tests\MonologWrapper\Examples\Deep\Example4;

class MonologWrapperTest extends TestCase {

  private $loader;
  private $container;

  public function setUp(): void {
    $this->container = new ContainerBuilder();
    $this->loader = new YamlFileLoader($this->container, new FileLocator(__DIR__));
  }

  public function tearDown(): void {
    MonologWrapper::clear();
  }

  public function testGetLoggerBeforeInit() {
    $this->loader->load(__DIR__ . '/resources/MonologWrapperTests/services_default_logger.yml');

    $result1 = $this->container->get('service.example.1')->doSomething();

    $this->assertInstanceOf(DevNullLogger::class, $result1['logger']);
  }

  public function testNoDefinedLoggersGetLoggerWithoutParameter() {
    $this->loader->load(__DIR__ . '/resources/MonologWrapperTests/services_without_logger.yml');
    MonologWrapper::init($this->container);

    $result1 = $this->container->get('service.example.4')->doSomething();

    $this->assertInstanceOf(DevNullLogger::class, $result1['logger']);
  }

  public function testDefaultDefinedLoggerGetLoggerWithoutParameter() {
    $this->loader->load(__DIR__ . '/resources/MonologWrapperTests/services_default_logger.yml');
    MonologWrapper::init($this->container);

    $result1 = $this->container->get('service.example.4')->doSomething();

    $this->assertInstanceOf(LevelLogger::class, $result1['logger']);
    $this->assertEquals('default', $result1['logger']->getName());
  }

  public function testNoDefinedLoggers() {
    $this->loader->load(__DIR__ . '/resources/MonologWrapperTests/services_without_logger.yml');
    MonologWrapper::init($this->container);

    $result1 = $this->container->get('service.example.1')->doSomething();

    $this->assertInstanceOf(DevNullLogger::class, $result1['logger']);
  }

  public function testDefaultLogger() {
    $this->loader->load(__DIR__ . '/resources/MonologWrapperTests/services_default_logger.yml');
    MonologWrapper::init($this->container);

    $result1 = $this->container->get('service.example.1')->doSomething();
    $result2 = $this->container->get('service.example.2')->doSomething();
    $result3 = $this->container->get('service.example.3')->doSomething();

    $this->assertInstanceOf(LevelLogger::class, $result1['logger']);
    $this->assertInstanceOf(LevelLogger::class, $result2['logger']);
    $this->assertInstanceOf(LevelLogger::class, $result3['logger']);

    $this->assertEquals(Example1::class, $result1['logger']->getName());
    $this->assertEquals(Example2::class, $result2['logger']->getName());
    $this->assertEquals(Example3::class, $result3['logger']->getName());
  }

  public function testNamespaceLogger() {
    $this->loader->load(__DIR__ . '/resources/MonologWrapperTests/services_namespace_logger.yml');
    MonologWrapper::init($this->container);

    $result1 = $this->container->get('service.example.1')->doSomething();
    $result2 = $this->container->get('service.example.2')->doSomething();
    $result3 = $this->container->get('service.example.3')->doSomething();

    $this->assertInstanceOf(LevelLogger::class, $result1['logger']);
    $this->assertInstanceOf(LevelLogger::class, $result2['logger']);
    $this->assertInstanceOf(LevelLogger::class, $result3['logger']);

    $this->assertEquals('Smartling\Tests', $result1['logger']->getName());
    $this->assertEquals('Smartling\Tests', $result2['logger']->getName());
    $this->assertEquals('Smartling\Tests', $result3['logger']->getName());
  }

  public function testDeepNamespaceLoggers() {
    $this->loader->load(__DIR__ . '/resources/MonologWrapperTests/services_deep_namespace_logger.yml');
    MonologWrapper::init($this->container);

    $result1 = $this->container->get('service.example.1')->doSomething();
    $result2 = $this->container->get('service.example.2')->doSomething();
    $result3 = $this->container->get('service.example.3')->doSomething();

    $this->assertInstanceOf(LevelLogger::class, $result1['logger']);
    $this->assertInstanceOf(LevelLogger::class, $result2['logger']);
    $this->assertInstanceOf(LevelLogger::class, $result3['logger']);

    $this->assertEquals('Smartling\Tests', $result1['logger']->getName());
    $this->assertEquals('Smartling\Tests', $result2['logger']->getName());
    $this->assertEquals('Smartling\Tests\MonologWrapper\Examples\Deep', $result3['logger']->getName());
  }

  public function testNamespaceAndClassLoggers() {
    $this->loader->load(__DIR__ . '/resources/MonologWrapperTests/services_namespace_and_class_logger.yml');
    MonologWrapper::init($this->container);

    $result1 = $this->container->get('service.example.1')->doSomething();
    $result2 = $this->container->get('service.example.2')->doSomething();
    $result3 = $this->container->get('service.example.3')->doSomething();
    $result4 = $this->container->get('service.example.4')->doSomething(true);

    $this->assertInstanceOf(LevelLogger::class, $result1['logger']);
    $this->assertInstanceOf(LevelLogger::class, $result2['logger']);
    $this->assertInstanceOf(LevelLogger::class, $result3['logger']);
    $this->assertInstanceOf(LevelLogger::class, $result4['logger']);

    $this->assertEquals('Smartling\Tests', $result1['logger']->getName());
    $this->assertEquals('Smartling\Tests', $result2['logger']->getName());
    $this->assertEquals('Smartling\Tests\MonologWrapper\Examples\Deep', $result3['logger']->getName());
    $this->assertEquals(Example4::class, $result4['logger']->getName());
  }
}
