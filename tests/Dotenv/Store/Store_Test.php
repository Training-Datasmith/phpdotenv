<?php

declare(strict_types=1);

namespace Dotenv\Tests\Store;

use Dotenv\Exception\Invalid_Encoding_Exception;
use Dotenv\Store\File\Paths;
use Dotenv\Store\File\Reader;
use Dotenv\Store\Store_Builder;
use PHPUnit\Framework\TestCase;

final class Store_Test extends TestCase
{
    /**
     * @var string
     */
    private static $folder;

    /**
     * @beforeClass
     *
     * @return void
     */
    public static function set_folder()
    {
        self::$folder = \dirname(\dirname(__DIR__)).'/fixtures/env';
    }

    public function test_basic_read_direct()
    {
        self::assertSame(
            [
                self::$folder.\DIRECTORY_SEPARATOR.'.env' => "FOO=bar\nBAR=baz\nSPACED=\"with spaces\"\n\nNULL=\n",
            ],
            Reader::read(
                Paths::file_paths([self::$folder], ['.env'])
            )
        );
    }

    public function test_basic_read()
    {
        $builder = Store_Builder::create_with_default_name()
            ->add_path(self::$folder);

        self::assertSame(
            "FOO=bar\nBAR=baz\nSPACED=\"with spaces\"\n\nNULL=\n",
            $builder->make()->read()
        );
    }

    public function test_basic_read_windows_encoding()
    {
        $builder = Store_Builder::create_with_no_names()
            ->add_path(self::$folder)
            ->add_name('windows.env')
            ->file_encoding('Windows-1252');

        self::assertSame(
            "MBW=\"ñá\"\n",
            $builder->make()->read()
        );
    }

    public function test_basic_read_bad_encoding()
    {
        $builder = Store_Builder::create_with_no_names()
            ->add_path(self::$folder)
            ->add_name('windows.env')
            ->file_encoding('Windowss-1252');

        $this->expectException(Invalid_Encoding_Exception::class);
        $this->expectExceptionMessage('Illegal character encoding [Windowss-1252] specified.');

        $builder->make()->read();
    }

    public function test_file_read_multiple_short_circuit_mode_direct()
    {
        self::assertSame(
            [
                self::$folder.\DIRECTORY_SEPARATOR.'.env' => "FOO=bar\nBAR=baz\nSPACED=\"with spaces\"\n\nNULL=\n",
            ],
            Reader::read(
                Paths::file_paths([self::$folder], ['.env', 'example.env'])
            )
        );
    }

    public function test_file_read_multiple_short_circuit_mode()
    {
        $builder = Store_Builder::create_with_no_names()
            ->add_path(self::$folder)
            ->add_name('.env')
            ->add_name('example.env')
            ->short_circuit();

        self::assertSame(
            "FOO=bar\nBAR=baz\nSPACED=\"with spaces\"\n\nNULL=\n",
            $builder->make()->read()
        );
    }

    public function test_file_read_multiple_without_short_circuit_mode_direct()
    {
        self::assertSame(
            [
                self::$folder.\DIRECTORY_SEPARATOR.'.env'        => "FOO=bar\nBAR=baz\nSPACED=\"with spaces\"\n\nNULL=\n",
                self::$folder.\DIRECTORY_SEPARATOR.'example.env' => "EG=\"example\"\n",
            ],
            Reader::read(
                Paths::file_paths([self::$folder], ['.env', 'example.env']),
                false
            )
        );
    }

    public function test_file_read_multiple_without_short_circuit_mode()
    {
        $builder = Store_Builder::create_with_default_name()
            ->add_path(self::$folder)
            ->add_name('example.env');

        self::assertSame(
            "FOO=bar\nBAR=baz\nSPACED=\"with spaces\"\n\nNULL=\n\nEG=\"example\"\n",
            $builder->make()->read()
        );
    }
    public function test_file_read_with_utf8_with_bom_encoding()
    {
        self::assertSame(
            [
                self::$folder.\DIRECTORY_SEPARATOR.'utf8-with-bom-encoding.env' => "FOO=bar\nBAR=baz\nSPACED=\"with spaces\"\n",
            ],
            Reader::read(
                Paths::file_paths([self::$folder], ['utf8-with-bom-encoding.env'])
            )
        );
    }
}
