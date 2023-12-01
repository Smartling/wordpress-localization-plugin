<?php

namespace Smartling\Tests\MonologWrapper;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Smartling\MonologWrapper\Logger\LevelLogger;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Vendor\Monolog\Handler\HandlerInterface;
use Smartling\Vendor\Symfony\Component\Config\FileLocator;
use Smartling\Vendor\Symfony\Component\DependencyInjection\ContainerBuilder;
use Smartling\Vendor\Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class LevelLoggerTest extends TestCase {

  private YamlFileLoader $loader;
  private ContainerBuilder $container;

  public function setUp(): void {
    $this->container = new ContainerBuilder();
    $this->loader = new YamlFileLoader($this->container, new FileLocator(__DIR__));
  }

  public function tearDown(): void {
    MonologWrapper::clear();
  }

  public function testDebugLevel() {
    $this->loader->load(__DIR__ . '/resources/LevelLoggerTests/services_debug.yml');
    MonologWrapper::init($this->container);

    $result = $this->container->get('service.example.1')->doSomething();

    $this->assertEquals([
      'debug' => true,
      'info' => true,
      'notice' => true,
      'warning' => true,
      'error' => true,
      'critical' => true,
      'alert' => true,
      'emergency' => true,
    ], $result['records']);
  }

  public function testInfoLevel() {
    $this->loader->load(__DIR__ . '/resources/LevelLoggerTests/services_info.yml');
    MonologWrapper::init($this->container);

    $result = $this->container->get('service.example.1')->doSomething();

    $this->assertEquals([
      'debug' => false,
      'info' => true,
      'notice' => true,
      'warning' => true,
      'error' => true,
      'critical' => true,
      'alert' => true,
      'emergency' => true,
    ], $result['records']);
  }

  public function testNoticeLevel() {
    $this->loader->load(__DIR__ . '/resources/LevelLoggerTests/services_notice.yml');
    MonologWrapper::init($this->container);

    $result = $this->container->get('service.example.1')->doSomething();

    $this->assertEquals([
      'debug' => false,
      'info' => false,
      'notice' => true,
      'warning' => true,
      'error' => true,
      'critical' => true,
      'alert' => true,
      'emergency' => true,
    ], $result['records']);
  }

  public function testWarningLevel() {
    $this->loader->load(__DIR__ . '/resources/LevelLoggerTests/services_warning.yml');
    MonologWrapper::init($this->container);

    $result = $this->container->get('service.example.1')->doSomething();

    $this->assertEquals([
      'debug' => false,
      'info' => false,
      'notice' => false,
      'warning' => true,
      'error' => true,
      'critical' => true,
      'alert' => true,
      'emergency' => true,
    ], $result['records']);
  }

  public function testErrorLevel() {
    $this->loader->load(__DIR__ . '/resources/LevelLoggerTests/services_error.yml');
    MonologWrapper::init($this->container);

    $result = $this->container->get('service.example.1')->doSomething();

    $this->assertEquals([
      'debug' => false,
      'info' => false,
      'notice' => false,
      'warning' => false,
      'error' => true,
      'critical' => true,
      'alert' => true,
      'emergency' => true,
    ], $result['records']);
  }

  public function testCriticalLevel() {
    $this->loader->load(__DIR__ . '/resources/LevelLoggerTests/services_critical.yml');
    MonologWrapper::init($this->container);

    $result = $this->container->get('service.example.1')->doSomething();

    $this->assertEquals([
      'debug' => false,
      'info' => false,
      'notice' => false,
      'warning' => false,
      'error' => false,
      'critical' => true,
      'alert' => true,
      'emergency' => true,
    ], $result['records']);
  }

  public function testAlertLevel() {
    $this->loader->load(__DIR__ . '/resources/LevelLoggerTests/services_alert.yml');
    MonologWrapper::init($this->container);

    $result = $this->container->get('service.example.1')->doSomething();

    $this->assertEquals([
      'debug' => false,
      'info' => false,
      'notice' => false,
      'warning' => false,
      'error' => false,
      'critical' => false,
      'alert' => true,
      'emergency' => true,
    ], $result['records']);
  }

  public function testEmergencyLevel() {
    $this->loader->load(__DIR__ . '/resources/LevelLoggerTests/services_emergency.yml');
    MonologWrapper::init($this->container);

    $result = $this->container->get('service.example.1')->doSomething();

    $this->assertEquals([
      'debug' => false,
      'info' => false,
      'notice' => false,
      'warning' => false,
      'error' => false,
      'critical' => false,
      'alert' => false,
      'emergency' => true,
    ], $result['records']);
  }

  public function testWithoutStringContext(): void
  {
      $x = new LevelLogger(name: 'test', handlers: [$this->getHandler(expected: 'message')]);
      $x->info('message');
  }

    /**
     * @dataProvider providerStringContext
     */
  public function testStringContext(array $context, string $expected): void
  {
      $x = new LevelLogger(name: 'test', handlers: [$this->getHandler(expected: $expected)]);
      $x->withStringContext($context, function () use ($x) {
          $x->info('message');
      });
  }

  public function testNestedStringContext(): void
  {
      $x = new LevelLogger(name: 'test', handlers: [$this->getHandler(expected: 'message, testContext="value", nestedContext="nested"')]);
      $x->withStringContext(['testContext' => 'value'], function () use ($x) {
          $x->withStringContext(['nestedContext' => 'nested'], function () use ($x) {
              $x->info('message');
          });
      });
  }

  public function providerStringContext(): array
  {
      return [
          'empty string context' => [
              [],
              'message',
          ],
          'single string context value' => [
              ['testContext' => 'value'],
              'message, testContext="value"',
          ],
          'multiple string context values' => [
              ['testContext' => 'value', 'numericValue' => 2, 'arrayValue' => ['a' => 'b']],
              'message, testContext="value", numericValue="2", arrayValue="Non-scalar: {\"a\":\"b\"}"',
          ],
      ];
  }

  private function getHandler(string $expected): HandlerInterface|MockObject
  {
      $handler = $this->createMock(HandlerInterface::class);
      $handler->method('isHandling')->willReturn(true);
      $handler->expects($this->once())->method('handle')->willReturnCallback(function (array $record) use ($expected) {
          $this->assertEquals($expected, $record['message']);
      });

      return $handler;
  }
}
