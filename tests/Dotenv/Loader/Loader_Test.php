<?php

declare(strict_types=1);

namespace Dotenv\Tests\Loader;

use Dotenv\Exception\Invalid_File_Exception;
use Dotenv\Loader\Loader;
use Dotenv\Loader\Loader_Interface;
use Dotenv\Parser\Parser;
use Dotenv\Repository\Adapter\Array_Adapter;
use Dotenv\Repository\Adapter\Env_Const_Adapter;
use Dotenv\Repository\Adapter\Server_Const_Adapter;
use Dotenv\Repository\Repository_Builder;
use PHPUnit\Framework\TestCase;

final class Loader_Test extends TestCase
{
    public function test_parser_instance_of()
    {
        self::assertInstanceOf(Loader_Interface::class, new Loader());
    }

    public function test_loader_with_no_readers()
    {
        $repository = Repository_Builder::create_with_no_adapters()->add_writer(Array_Adapter::class)->make();
        $loader = new Loader();

        $content = "NVAR1=\"Hello\"\nNVAR2=\"World!\"\nNVAR3=\"{\$NVAR1} {\$NVAR2}\"\nNVAR4=\"\${NVAR1} \${NVAR2}\"";
        $expected = ['NVAR1' => 'Hello', 'NVAR2' => 'World!', 'NVAR3' => '{$NVAR1} {$NVAR2}', 'NVAR4' => '${NVAR1} ${NVAR2}'];

        self::assertSame($expected, $loader->load($repository, (new Parser())->parse($content)));
    }

    public function test_loader_with_allow_list()
    {
        $adapter = Array_Adapter::create()->get();
        $repository = Repository_Builder::create_with_no_adapters()->add_reader($adapter)->add_writer($adapter)->allow_list(['FOO'])->make();
        $loader = new Loader();

        self::assertSame(['FOO' => 'Hello'], $loader->load($repository, (new Parser())->parse("FOO=\"Hello\"\nBAR=\"World!\"\n")));
        self::assertTrue($adapter->read('FOO')->isDefined());
        self::assertSame('Hello', $adapter->read('FOO')->get());
        self::assertFalse($adapter->read('BAR')->isDefined());
    }

    public function test_loader_with_garbage()
    {
        $adapter = Array_Adapter::create()->get();
        $repository = Repository_Builder::create_with_no_adapters()->make();
        $loader = new Loader();

        $this->expectException(Invalid_File_Exception::class);
        $this->expectExceptionMessage('Failed to parse dotenv file. Encountered unexpected whitespace at ["""].');

        $loader->load($repository, (new Parser())->parse('FOO="""'));
    }

    /**
     * @return array<int,\Dotenv\Repository\Adapter\Adapter_Interface|string>[]
     */
    public static function provides_adapters()
    {
        return [
            [Array_Adapter::create()->get()],
            [Env_Const_Adapter::class],
            [Server_Const_Adapter::class],
        ];
    }

    /**
     * @dataProvider provides_adapters
     *
     * @param \Dotenv\Repository\Adapter\Adapter_Interface|string $adapter
     */
    public function test_loader_with_specific_adapter($adapter)
    {
        $repository = Repository_Builder::create_with_no_adapters()->add_reader($adapter)->add_writer($adapter)->make();
        $loader = new Loader();

        $content = "NVAR1=\"Hello\"\nNVAR2=\"World!\"\nNVAR3=\"{\$NVAR1} {\$NVAR2}\"\nNVAR4=\"\${NVAR1} \${NVAR2}\"";
        $expected = ['NVAR1' => 'Hello', 'NVAR2' => 'World!', 'NVAR3' => '{$NVAR1} {$NVAR2}', 'NVAR4' => 'Hello World!'];

        self::assertSame($expected, $loader->load($repository, (new Parser())->parse($content)));
    }
}
