<?php

namespace Smartling\Tests\Smartling;

use PHPUnit\Framework\TestCase;
use Smartling\DebugTrait;

class DebugTraitTest extends TestCase
{
    /**
     * @dataProvider fatalErrorTypeProvider
     */
    public function testOnlyRequestTerminatingErrorsCountAsFatal(int $errorType, bool $expected, string $label)
    {
        $subject = new class {
            use DebugTrait;
        };

        $this->assertSame($expected, $subject::isFatalError($errorType), "$label was classified incorrectly");
    }

    public function fatalErrorTypeProvider(): array
    {
        return [
            // These end the request and are worth an emergency.
            [E_ERROR, true, 'E_ERROR'],
            [E_PARSE, true, 'E_PARSE'],
            [E_CORE_ERROR, true, 'E_CORE_ERROR'],
            [E_COMPILE_ERROR, true, 'E_COMPILE_ERROR'],
            [E_USER_ERROR, true, 'E_USER_ERROR'],
            [E_RECOVERABLE_ERROR, true, 'E_RECOVERABLE_ERROR'],
            // These do not, and previously flooded the log as false emergencies.
            [E_USER_DEPRECATED, false, 'E_USER_DEPRECATED'],
            [E_DEPRECATED, false, 'E_DEPRECATED'],
            [E_WARNING, false, 'E_WARNING'],
            [E_NOTICE, false, 'E_NOTICE'],
            [E_USER_WARNING, false, 'E_USER_WARNING'],
            [E_USER_NOTICE, false, 'E_USER_NOTICE'],
            [E_CORE_WARNING, false, 'E_CORE_WARNING'],
            [E_COMPILE_WARNING, false, 'E_COMPILE_WARNING'],
        ];
    }

    /**
     * The old message rendered the decimal error type behind an "0x" prefix, so
     * E_USER_DEPRECATED showed up as the meaningless "0x16384".
     */
    public function testErrorTypeIsNamedRatherThanMislabelledAsHex()
    {
        $subject = new class {
            use DebugTrait;
        };

        $this->assertSame('E_PARSE', $subject::getErrorTypeName(E_PARSE));
        $this->assertSame('E_USER_DEPRECATED', $subject::getErrorTypeName(E_USER_DEPRECATED));
        $this->assertStringContainsString('12345', $subject::getErrorTypeName(12345), 'Unknown types should still be reported');
    }
}
