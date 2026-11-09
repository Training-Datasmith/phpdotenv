<?php

declare(strict_types=1);

namespace Dotenv\Tests\Repository\Adapter;

use Dotenv\Repository\Adapter\Server_Const_Adapter;
use PHPUnit\Framework\TestCase;

final class Server_Const_Adapter_Test extends TestCase
{
    public function test_good_read()
    {
        $_SERVER['CONST_TEST'] = 'foo bar baz';
        $value = self::create_adapter()->read('CONST_TEST');
        self::assertTrue($value->isDefined());
        self::assertSame('foo bar baz', $value->get());
    }

    public function test_false_read()
    {
        $_SERVER['CONST_TEST'] = false;
        $value = self::create_adapter()->read('CONST_TEST');
        self::assertTrue($value->isDefined());
        self::assertSame('false', $value->get());
    }

    public function test_true_read()
    {
        $_SERVER['CONST_TEST'] = true;
        $value = self::create_adapter()->read('CONST_TEST');
        self::assertTrue($value->isDefined());
        self::assertSame('true', $value->get());
    }

    public function test_bad_type_read()
    {
        $_SERVER['CONST_TEST'] = [123];
        $value = self::create_adapter()->read('CONST_TEST');
        self::assertFalse($value->isDefined());
    }

    public function test_undefined_read()
    {
        unset($_SERVER['CONST_TEST']);
        $value = self::create_adapter()->read('CONST_TEST');
        self::assertFalse($value->isDefined());
    }

    public function test_good_write()
    {
        self::assertTrue(self::create_adapter()->write('CONST_TEST', 'foo'));
        self::assertSame('foo', $_SERVER['CONST_TEST']);
    }

    public function test_empty_write()
    {
        self::assertTrue(self::create_adapter()->write('CONST_TEST', ''));
        self::assertSame('', $_SERVER['CONST_TEST']);
    }

    public function test_good_delete()
    {
        self::assertTrue(self::create_adapter()->delete('CONST_TEST'));
        self::assertFalse(isset($_SERVER['CONST_TEST']));
    }

    /**
     * @return \Dotenv\Repository\Adapter\Adapter_Interface
     */
    private static function create_adapter()
    {
        return Server_Const_Adapter::create()->get();
    }
}
