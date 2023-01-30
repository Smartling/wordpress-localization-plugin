<?php

namespace Smartling\Tests\Smartling\Extensions\Acf;

use PHPUnit\Framework\TestCase;
use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\Exception\EntityNotFoundException;
use Smartling\Exception\SmartlingDirectRunRuntimeException;
use Smartling\Extensions\AcfOptionPages\AcfOptionEntity;

class AcfOptionEntityTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        defined('ARRAY_A') || define('ARRAY_A', 'ARRAY_A');
        defined('OBJECT') || define('OBJECT', 'OBJECT');
    }

    public function testGetAcfOptionEntityQuery()
    {
        $db = $this->createMock(SmartlingToCMSDatabaseAccessWrapperInterface::class);
        $db->method('completeTableName')->willReturnCallback(function ($tableName) {
            return 'wp_' . $tableName;
        });
        $db->expects($this->once())->method('fetch')->with("SELECT `option_name` FROM `wp_options` WHERE ( `option_name` LIKE 'options_%' )")->willReturn([]);
        $x = new AcfOptionEntity($db);
        try {
            $x->get('guid');
        } catch (EntityNotFoundException|SmartlingDirectRunRuntimeException) {
            // Exceptions differ if running as integration test
        }
    }
}
