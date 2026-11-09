<?php

declare(strict_types=1);

namespace Dotenv\Tests\Parser;

use Dotenv\Exception\Invalid_File_Exception;
use Dotenv\Parser\Entry;
use Dotenv\Parser\Parser;
use Dotenv\Parser\Parser_Interface;
use Dotenv\Parser\Value;
use PHPUnit\Framework\TestCase;

final class Parser_Test extends TestCase
{
    public function test_parser_instance_of()
    {
        self::assertInstanceOf(Parser_Interface::class, new Parser());
    }

    public function test_full_parse()
    {
        $result = (new Parser())->parse("FOO=BAR\nFOO\nFOO=\"BAR  \n\"\nFOO=\"\\n\"");

        self::assertIsArray($result);
        self::assertCount(4, $result);

        $this->check_positive_entry($result[0], 'FOO', 'BAR');
        $this->check_empty_entry($result[1], 'FOO');
        $this->check_positive_entry($result[2], 'FOO', "BAR  \n");
        $this->check_positive_entry($result[3], 'FOO', "\n");
    }

    public function test_bad_escape_parse()
    {
        $this->expectException(Invalid_File_Exception::class);
        $this->expectExceptionMessage('Failed to parse dotenv file. Encountered an unexpected escape sequence at ["\q"].');

        (new Parser())->parse('FOO="\q"');
    }

    public function test_parse_invalid_spaces()
    {
        $this->expectException(Invalid_File_Exception::class);
        $this->expectExceptionMessage('Failed to parse dotenv file. Encountered unexpected whitespace at [bar baz].');

        (new Parser())->parse("FOO=bar baz\n");
    }

    public function test_parse_stray_equals()
    {
        $this->expectException(Invalid_File_Exception::class);
        $this->expectExceptionMessage('Failed to parse dotenv file. Encountered an unexpected equals at [=].');

        (new Parser())->parse("=\n");
    }

    public function test_parse_invalid_name()
    {
        $this->expectException(Invalid_File_Exception::class);
        $this->expectExceptionMessage('Failed to parse dotenv file. Encountered an invalid name at [FOO_ASD!].');

        (new Parser())->parse('FOO_ASD!=BAZ');
    }

    /**
     * @param \Dotenv\Parser\Entry $entry
     * @param string               $name
     * @param string               $chars
     * @param int[]                $vars
     *
     * @return void
     */
    private function check_positive_entry(Entry $entry, string $name, string $chars, array $vars = [])
    {
        self::assertInstanceOf(Entry::class, $entry);
        self::assertSame($name, $entry->get_name());
        self::assertTrue($entry->get_value()->isDefined());

        $value = $entry->get_value()->get();
        self::assertInstanceOf(Value::class, $value);
        self::assertSame($chars, $value->get_chars());
        self::assertSame($vars, $value->get_vars());
    }

    /**
     * @param \Dotenv\Parser\Entry $entry
     * @param string               $name
     *
     * @return void
     */
    private function check_empty_entry(Entry $entry, string $name)
    {
        self::assertInstanceOf(Entry::class, $entry);
        self::assertSame('FOO', $entry->get_name());
        self::assertFalse($entry->get_value()->isDefined());
    }
}
