<?php

declare(strict_types=1);

namespace Dotenv\Tests\Repository\Adapter;

use Dotenv\Repository\Adapter\Putenv_Adapter;
use PHPUnit\Framework\TestCase;

final class Putenv_Adapter_Test extends TestCase
{
    public function test_good_read()
    {
        \putenv('CONST_TEST=foo bar baz');
        $value = self::create_adapter()->read('CONST_TEST');
        self::assertTrue($value->isDefined());
        self::assertSame('foo bar baz', $value->get());
    }

    public function test_undefined_read()
    {
        \putenv('CONST_TEST');
        $value = self::create_adapter()->read('CONST_TEST');
        self::assertFalse($value->isDefined());
    }

    public function test_good_write()
    {
        self::assertTrue(self::create_adapter()->write('CONST_TEST', 'foo'));
        self::assertSame('foo', \getenv('CONST_TEST'));
    }

    public function test_empty_write()
    {
        self::assertTrue(self::create_adapter()->write('CONST_TEST', ''));
        self::assertSame('', \getenv('CONST_TEST'));
    }

    public function test_good_delete()
    {
        self::assertTrue(self::create_adapter()->delete('CONST_TEST'));
        self::assertFalse(\getenv('CONST_TEST'));
    }

    /**
     * @return \Dotenv\Repository\Adapter\Adapter_Interface
     */
    private static function create_adapter()
    {
        return Putenv_Adapter::create()->get();
    }
}
