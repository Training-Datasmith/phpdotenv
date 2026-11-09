<?php

declare(strict_types=1);

namespace Dotenv\Tests\Repository\Adapter;

use Dotenv\Repository\Adapter\Array_Adapter;
use PHPUnit\Framework\TestCase;

final class Array_Adapter_Test extends TestCase
{
    public function test_good_read()
    {
        $adapter = self::create_adapter();
        $adapter->write('CONST_TEST', 'foo bar baz');
        $value = $adapter->read('CONST_TEST');
        self::assertTrue($value->isDefined());
        self::assertSame('foo bar baz', $value->get());
    }

    public function test_undefined_read()
    {
        $adapter = self::create_adapter();
        unset($_ENV['CONST_TEST']);
        $value = $adapter->read('CONST_TEST');
        self::assertFalse($value->isDefined());
    }

    public function test_good_write()
    {
        $adapter = self::create_adapter();
        self::assertTrue($adapter->write('CONST_TEST', 'foo'));
        self::assertSame('foo', $adapter->read('CONST_TEST')->get());
    }

    public function test_empty_write()
    {
        $adapter = self::create_adapter();
        self::assertTrue($adapter->write('CONST_TEST', ''));
        self::assertSame('', $adapter->read('CONST_TEST')->get());
    }

    public function test_good_delete()
    {
        $adapter = self::create_adapter();
        self::assertTrue($adapter->delete('CONST_TEST'));
        self::assertFalse($adapter->read('CONST_TEST')->isDefined());
    }

    /**
     * @return \Dotenv\Repository\Adapter\Adapter_Interface
     */
    private static function create_adapter()
    {
        return Array_Adapter::create()->get();
    }
}
