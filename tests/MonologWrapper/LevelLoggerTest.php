<?php

namespace Smartling\Tests;

use PHPUnit\Framework\TestCase;
use Smartling\MonologWrapper\MonologWrapper;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Class LevelLoggerTest
 *
 * @package Tests
 */
class LevelLoggerTest extends TestCase {

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
  public function setUp(): void {
    $this->container = new ContainerBuilder();
    $this->loader = new YamlFileLoader($this->container, new FileLocator(__DIR__));
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown(): void {
    MonologWrapper::clear();
  }

  /**
   * Test logger debug level.
   */
  public function testDebugLevel() {
    $this->loader->load(__DIR__ . '/resources/LevelLoggerTests/services_debug.yml');
    MonologWrapper::init($this->container);

    $result = $this->container->get('service.example.1')->doSomething();

    $this->assertEquals($result['records'], [
      'debug' => true,
      'info' => true,
      'notice' => true,
      'warning' => true,
      'error' => true,
      'critical' => true,
      'alert' => true,
      'emergency' => true,
    ]);
  }

  /**
   * Test logger info level.
   */
  public function testInfoLevel() {
    $this->loader->load(__DIR__ . '/resources/LevelLoggerTests/services_info.yml');
    MonologWrapper::init($this->container);

    $result = $this->container->get('service.example.1')->doSomething();

    $this->assertEquals($result['records'], [
      'debug' => false,
      'info' => true,
      'notice' => true,
      'warning' => true,
      'error' => true,
      'critical' => true,
      'alert' => true,
      'emergency' => true,
    ]);
  }

  /**
   * Test logger notice level.
   */
  public function testNoticeLevel() {
    $this->loader->load(__DIR__ . '/resources/LevelLoggerTests/services_notice.yml');
    MonologWrapper::init($this->container);

    $result = $this->container->get('service.example.1')->doSomething();

    $this->assertEquals($result['records'], [
      'debug' => false,
      'info' => false,
      'notice' => true,
      'warning' => true,
      'error' => true,
      'critical' => true,
      'alert' => true,
      'emergency' => true,
    ]);
  }

  /**
   * Test logger warning level.
   */
  public function testWarningLevel() {
    $this->loader->load(__DIR__ . '/resources/LevelLoggerTests/services_warning.yml');
    MonologWrapper::init($this->container);

    $result = $this->container->get('service.example.1')->doSomething();

    $this->assertEquals($result['records'], [
      'debug' => false,
      'info' => false,
      'notice' => false,
      'warning' => true,
      'error' => true,
      'critical' => true,
      'alert' => true,
      'emergency' => true,
    ]);
  }

  /**
   * Test logger error level.
   */
  public function testErrorLevel() {
    $this->loader->load(__DIR__ . '/resources/LevelLoggerTests/services_error.yml');
    MonologWrapper::init($this->container);

    $result = $this->container->get('service.example.1')->doSomething();

    $this->assertEquals($result['records'], [
      'debug' => false,
      'info' => false,
      'notice' => false,
      'warning' => false,
      'error' => true,
      'critical' => true,
      'alert' => true,
      'emergency' => true,
    ]);
  }

  /**
   * Test logger critical level.
   */
  public function testCriticalLevel() {
    $this->loader->load(__DIR__ . '/resources/LevelLoggerTests/services_critical.yml');
    MonologWrapper::init($this->container);

    $result = $this->container->get('service.example.1')->doSomething();

    $this->assertEquals($result['records'], [
      'debug' => false,
      'info' => false,
      'notice' => false,
      'warning' => false,
      'error' => false,
      'critical' => true,
      'alert' => true,
      'emergency' => true,
    ]);
  }

  /**
   * Test logger alert level.
   */
  public function testAlertLevel() {
    $this->loader->load(__DIR__ . '/resources/LevelLoggerTests/services_alert.yml');
    MonologWrapper::init($this->container);

    $result = $this->container->get('service.example.1')->doSomething();

    $this->assertEquals($result['records'], [
      'debug' => false,
      'info' => false,
      'notice' => false,
      'warning' => false,
      'error' => false,
      'critical' => false,
      'alert' => true,
      'emergency' => true,
    ]);
  }

  /**
   * Test logger emergency level.
   */
  public function testEmergencyLevel() {
    $this->loader->load(__DIR__ . '/resources/LevelLoggerTests/services_emergency.yml');
    MonologWrapper::init($this->container);

    $result = $this->container->get('service.example.1')->doSomething();

    $this->assertEquals($result['records'], [
      'debug' => false,
      'info' => false,
      'notice' => false,
      'warning' => false,
      'error' => false,
      'critical' => false,
      'alert' => false,
      'emergency' => true,
    ]);
  }

}
