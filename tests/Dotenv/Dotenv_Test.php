<?php

declare(strict_types=1);

namespace Dotenv\Tests;

use Dotenv\Dotenv;
use Dotenv\Exception\Invalid_Encoding_Exception;
use Dotenv\Exception\Invalid_Path_Exception;
use Dotenv\Loader\Loader;
use Dotenv\Parser\Parser;
use Dotenv\Repository\Repository_Builder;
use Dotenv\Store\Store_Builder;
use PHPUnit\Framework\TestCase;

final class Dotenv_Test extends TestCase
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
        self::$folder = \dirname(__DIR__).'/fixtures/env';
    }

    public function test_dotenv_throws_exception_if_unable_to_load_file()
    {
        $dotenv = Dotenv::create_mutable(__DIR__);

        $this->expectException(Invalid_Path_Exception::class);
        $this->expectExceptionMessage('Unable to read any of the environment file(s) at');

        $dotenv->load();
    }

    public function test_dotenv_throws_exception_if_unable_to_load_files()
    {
        $dotenv = Dotenv::create_mutable([__DIR__, __DIR__.'/foo/bar']);

        $this->expectException(Invalid_Path_Exception::class);
        $this->expectExceptionMessage('Unable to read any of the environment file(s) at');

        $dotenv->load();
    }

    public function test_dotenv_throws_exception_when_no_files()
    {
        $dotenv = Dotenv::create_mutable([]);

        $this->expectException(Invalid_Path_Exception::class);
        $this->expectExceptionMessage('At least one environment file path must be provided.');

        $dotenv->load();
    }

    public function test_dotenv_tries_paths_to_load()
    {
        $dotenv = Dotenv::create_mutable([__DIR__, self::$folder]);
        self::assertCount(4, $dotenv->load());
    }

    public function test_dotenv_tries_paths_to_load_twice()
    {
        $dotenv = Dotenv::create_mutable([__DIR__, self::$folder]);
        self::assertCount(4, $dotenv->load());

        $dotenv = Dotenv::create_immutable([__DIR__, self::$folder]);
        self::assertCount(0, $dotenv->load());
    }

    public function test_dotenv_tries_paths_to_safe_load()
    {
        $dotenv = Dotenv::create_mutable([__DIR__, self::$folder]);
        self::assertCount(4, $dotenv->safe_load());
    }

    public function test_dotenv_skips_loading_if_file_is_missing()
    {
        $dotenv = Dotenv::create_mutable(__DIR__);
        self::assertSame([], $dotenv->safe_load());
    }

    public function test_dotenv_loads_environment_vars()
    {
        $dotenv = Dotenv::create_mutable(self::$folder);
        self::assertSame(
            ['FOO' => 'bar', 'BAR' => 'baz', 'SPACED' => 'with spaces', 'NULL' => ''],
            $dotenv->load()
        );
        self::assertSame('bar', $_SERVER['FOO']);
        self::assertSame('baz', $_SERVER['BAR']);
        self::assertSame('with spaces', $_SERVER['SPACED']);
        self::assertEmpty($_SERVER['NULL']);
    }

    public function test_dotenv_loads_environment_vars_multiple_with_short_circuit_mode()
    {
        $dotenv = Dotenv::create_mutable(self::$folder, ['.env', 'example.env']);

        self::assertSame(
            ['FOO' => 'bar', 'BAR' => 'baz', 'SPACED' => 'with spaces', 'NULL' => ''],
            $dotenv->load()
        );
    }

    public function test_dotenv_loads_environment_vars_multiple_without_short_circuit_mode()
    {
        $dotenv = Dotenv::create_mutable(self::$folder, ['.env', 'example.env'], false);

        self::assertSame(
            ['FOO' => 'bar', 'BAR' => 'baz', 'SPACED' => 'with spaces', 'NULL' => '', 'EG' => 'example'],
            $dotenv->load()
        );
    }

    public function test_commented_dotenv_loads_environment_vars()
    {
        $dotenv = Dotenv::create_mutable(self::$folder, 'commented.env');
        $dotenv->load();
        self::assertSame('bar', $_SERVER['CFOO']);
        self::assertFalse(isset($_SERVER['CBAR']));
        self::assertFalse(isset($_SERVER['CZOO']));
        self::assertSame('with spaces', $_SERVER['CSPACED']);
        self::assertSame('a value with a # character', $_SERVER['CQUOTES']);
        self::assertSame('a value with a # character & a quote " character inside quotes', $_SERVER['CQUOTESWITHQUOTE']);
        self::assertEmpty($_SERVER['CNULL']);
        self::assertEmpty($_SERVER['EMPTY']);
        self::assertEmpty($_SERVER['EMPTY2']);
        self::assertSame('foo', $_SERVER['FOOO']);
    }

    public function test_quoted_dotenv_loads_environment_vars()
    {
        $dotenv = Dotenv::create_mutable(self::$folder, 'quoted.env');
        $dotenv->load();
        self::assertSame('bar', $_SERVER['QFOO']);
        self::assertSame('baz', $_SERVER['QBAR']);
        self::assertSame('with spaces', $_SERVER['QSPACED']);
        self::assertEmpty(\getenv('QNULL'));

        self::assertSame('pgsql:host=localhost;dbname=test', $_SERVER['QEQUALS']);
        self::assertSame('test some escaped characters like a quote (") or maybe a backslash (\\)', $_SERVER['QESCAPED']);
        self::assertSame('iiiiviiiixiiiiviiii\\n', $_SERVER['QSLASH']);
        self::assertSame('iiiiviiiixiiiiviiii\\\\n', $_SERVER['SQSLASH']);
    }

    public function test_large_dotenv_loads_environment_vars()
    {
        $dotenv = Dotenv::create_mutable(self::$folder, 'large.env');
        $dotenv->load();
        self::assertSame(2730, \strlen($_SERVER['LARGE']));
        self::assertSame(8192, \strlen($_SERVER['HUGE']));
    }

    public function test_dotenv_loads_multibyte_vars()
    {
        $dotenv = Dotenv::create_mutable(self::$folder, 'multibyte.env');
        $dotenv->load();
        self::assertSame('Ā ā Ă ă Ą ą Ć ć Ĉ ĉ Ċ ċ Č č Ď ď Đ đ Ē ē Ĕ ĕ Ė ė Ę ę Ě ě', $_SERVER['MB1']);
        self::assertSame('行内支付', $_SERVER['MB2']);
        self::assertSame('🚀', $_SERVER['APP_ENV']);
    }

    public function test_dotenv_loads_multibyte_utf8_vars()
    {
        $dotenv = Dotenv::create_mutable(self::$folder, 'multibyte.env', false, 'UTF-8');
        $dotenv->load();
        self::assertSame('Ā ā Ă ă Ą ą Ć ć Ĉ ĉ Ċ ċ Č č Ď ď Đ đ Ē ē Ĕ ĕ Ė ė Ę ę Ě ě', $_SERVER['MB1']);
        self::assertSame('行内支付', $_SERVER['MB2']);
        self::assertSame('🚀', $_SERVER['APP_ENV']);
    }

    public function test_dotenv_load_with_invalid_encoding()
    {
        $dotenv = Dotenv::create_mutable(self::$folder, 'multibyte.env', false, 'UTF-88');

        $this->expectException(Invalid_Encoding_Exception::class);
        $this->expectExceptionMessage('Illegal character encoding [UTF-88] specified.');

        $dotenv->load();
    }

    public function test_dotenv_loads_multibyte_windows_vars()
    {
        $dotenv = Dotenv::create_mutable(self::$folder, 'windows.env', false, 'Windows-1252');
        $dotenv->load();
        self::assertSame('ñá', $_SERVER['MBW']);
    }

    public function test_multiple_dotenv_loads_environment_vars()
    {
        $dotenv = Dotenv::create_mutable(self::$folder, 'multiple.env');
        $dotenv->load();
        self::assertSame('bar', $_SERVER['MULTI1']);
        self::assertSame('foo', $_SERVER['MULTI2']);
    }

    public function test_exported_dotenv_loads_environment_vars()
    {
        $dotenv = Dotenv::create_mutable(self::$folder, 'exported.env');
        $dotenv->load();
        self::assertSame('bar', $_SERVER['EFOO']);
        self::assertSame('baz', $_SERVER['EBAR']);
        self::assertSame('with spaces', $_SERVER['ESPACED']);
        self::assertSame('123', $_SERVER['EDQUOTED']);
        self::assertSame('456', $_SERVER['ESQUOTED']);
        self::assertEmpty($_SERVER['ENULL']);
    }

    public function test_dotenv_loads_env_globals()
    {
        $dotenv = Dotenv::create_mutable(self::$folder);
        $dotenv->load();
        self::assertSame('bar', $_SERVER['FOO']);
        self::assertSame('baz', $_SERVER['BAR']);
        self::assertSame('with spaces', $_SERVER['SPACED']);
        self::assertEmpty($_SERVER['NULL']);
    }

    public function test_dotenv_loads_server_globals()
    {
        $dotenv = Dotenv::create_mutable(self::$folder);
        $dotenv->load();
        self::assertSame('bar', $_ENV['FOO']);
        self::assertSame('baz', $_ENV['BAR']);
        self::assertSame('with spaces', $_ENV['SPACED']);
        self::assertEmpty($_ENV['NULL']);
    }

    public function test_dotenv_nested_environment_vars()
    {
        $dotenv = Dotenv::create_mutable(self::$folder, 'nested.env');
        $dotenv->load();
        self::assertSame('{$NVAR1} {$NVAR2}', $_ENV['NVAR3']); // not resolved
        self::assertSame('Hellō World!', $_ENV['NVAR4']);
        self::assertSame('$NVAR1 {NVAR2}', $_ENV['NVAR5']); // not resolved
        self::assertSame('Special Value', $_ENV['N.VAR6']); // new '.' (dot) in var name
        self::assertSame('Special Value', $_ENV['NVAR7']);  // nested '.' (dot) variable
        self::assertSame('', $_ENV['NVAR8']); // nested variable is empty string
        self::assertSame('', $_ENV['NVAR9']); // nested variable is empty string
        self::assertSame('${NVAR888}', $_ENV['NVAR10']); // nested variable is not set
        self::assertSame('NVAR1', $_ENV['NVAR11']);
        self::assertSame('Hellō', $_ENV['NVAR12']);
        self::assertSame('${${NVAR11}}', $_ENV['NVAR13']); // single quotes
        self::assertSame('${NVAR1} ${NVAR2}', $_ENV['NVAR14']); // single quotes
        self::assertSame('${NVAR1} ${NVAR2}', $_ENV['NVAR15']); // escaped
    }

    public function test_dotenv_null_file_argument_uses_default()
    {
        $dotenv = Dotenv::create_mutable(self::$folder, null);
        $dotenv->load();
        self::assertSame('bar', $_SERVER['FOO']);
    }

    /**
     * The fixture data has whitespace between the key and in the value string.
     *
     * Test that these keys are trimmed down.
     */
    public function test_dotenv_trimmed_keys()
    {
        $dotenv = Dotenv::create_mutable(self::$folder, 'quoted.env');
        $dotenv->load();
        self::assertSame('no space', $_SERVER['QWHITESPACE']);
    }

    public function test_dotenv_load_does_not_overwrite_env()
    {
        \putenv('IMMUTABLE=true');
        $dotenv = Dotenv::create_immutable(self::$folder, 'immutable.env');
        $dotenv->load();
        self::assertSame('true', \getenv('IMMUTABLE'));
    }

    public function test_dotenv_load_after_overload()
    {
        \putenv('IMMUTABLE=true');
        $dotenv = Dotenv::create_unsafe_mutable(self::$folder, 'immutable.env');
        $dotenv->load();
        self::assertSame('false', \getenv('IMMUTABLE'));
    }

    public function test_dotenv_overload_after_load()
    {
        \putenv('IMMUTABLE=true');
        $dotenv = Dotenv::create_unsafe_immutable(self::$folder, 'immutable.env');
        $dotenv->load();
        self::assertSame('true', \getenv('IMMUTABLE'));
    }

    public function test_dotenv_overload_does_overwrite_env()
    {
        $dotenv = Dotenv::create_unsafe_mutable(self::$folder, 'mutable.env');
        $dotenv->load();
        self::assertSame('true', \getenv('MUTABLE'));
    }

    public function test_dotenv_allows_special_characters()
    {
        $dotenv = Dotenv::create_unsafe_mutable(self::$folder, 'specialchars.env');
        $dotenv->load();
        self::assertSame('$a6^C7k%zs+e^.jvjXk', \getenv('SPVAR1'));
        self::assertSame('?BUty3koaV3%GA*hMAwH}B', \getenv('SPVAR2'));
        self::assertSame('jdgEB4{QgEC]HL))&GcXxokB+wqoN+j>xkV7K?m$r', \getenv('SPVAR3'));
        self::assertSame('22222:22#2^{', \getenv('SPVAR4'));
        self::assertSame('test some escaped characters like a quote " or maybe a backslash \\', \getenv('SPVAR5'));
        self::assertSame('secret!@', \getenv('SPVAR6'));
        self::assertSame('secret!@#', \getenv('SPVAR7'));
        self::assertSame('secret!@#', \getenv('SPVAR8'));
    }

    public function test_multiline_loading()
    {
        $dotenv = Dotenv::create_unsafe_mutable(self::$folder, 'multiline.env');
        $dotenv->load();
        self::assertSame("test\n     test\"test\"\n     test", \getenv('TEST'));
        self::assertSame("test\ntest", \getenv('TEST_ND'));
        self::assertSame('test\\ntest', \getenv('TEST_NS'));

        self::assertSame('https://vision.googleapis.com/v1/images:annotate?key=', \getenv('TEST_EQD'));
        self::assertSame('https://vision.googleapis.com/v1/images:annotate?key=', \getenv('TEST_EQS'));
    }

    public function test_empty_loading()
    {
        $dotenv = Dotenv::create_immutable(self::$folder, 'empty.env');
        self::assertSame(['EMPTY_VAR' => null], $dotenv->load());
    }

    public function test_unicode_var_names()
    {
        $dotenv = Dotenv::create_immutable(self::$folder, 'unicodevarnames.env');
        $dotenv->load();
        self::assertSame('Skybert', $_SERVER['AlbertÅberg']);
        self::assertSame('2022-04-01T00:00', $_SERVER['ДатаЗакрытияРасчетногоПериода']);
    }

    public function test_direct_constructor()
    {
        $repository = Repository_Builder::create_with_default_adapters()->make();
        $store = Store_Builder::create_with_default_name()->add_path(self::$folder)->make();

        $dotenv = new Dotenv($store, new Parser(), new Loader(), $repository);

        self::assertSame([
            'FOO'    => 'bar',
            'BAR'    => 'baz',
            'SPACED' => 'with spaces',
            'NULL'   => '',
        ], $dotenv->load());
    }

    public function test_dotenv_parse_example1()
    {
        $output = Dotenv::parse(
            "BASE_DIR=\"/var/webroot/project-root\"\nCACHE_DIR=\"\${BASE_DIR}/cache\"\nTMP_DIR=\"\${BASE_DIR}/tmp\"\n"
        );

        self::assertSame($output, [
            'BASE_DIR'  => '/var/webroot/project-root',
            'CACHE_DIR' => '/var/webroot/project-root/cache',
            'TMP_DIR'   => '/var/webroot/project-root/tmp',
        ]);
    }

    public function test_dotenv_parse_example2()
    {
        $output = Dotenv::parse("FOO=Bar\nBAZ=\"Hello \${FOO}\"");

        self::assertSame($output, ['FOO' => 'Bar', 'BAZ' => 'Hello Bar']);
    }

    public function test_dotenv_parse_empty_case()
    {
        $output = Dotenv::parse('');

        self::assertSame($output, []);
    }
}
