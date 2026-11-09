<?php

declare(strict_types=1);

namespace Dotenv\Tests\Parser;

use Dotenv\Parser\Entry;
use Dotenv\Parser\Entry_Parser;
use Dotenv\Parser\Value;
use GrahamCampbell\ResultType\Result;
use PHPUnit\Framework\TestCase;

final class Entry_Parser_Test extends TestCase
{
    public function test_basic_parse()
    {
        $result = Entry_Parser::parse('FOO=BAR');
        $this->check_positive_result($result, 'FOO', 'BAR');
    }

    public function test_null_parse()
    {
        $result = Entry_Parser::parse('FOO');
        $this->check_empty_result($result, 'FOO');
    }

    public function test_unicode_name_parse()
    {
        $result = Entry_Parser::parse('FOOƱ=BAZ');
        $this->check_positive_result($result, 'FOOƱ', 'BAZ');
    }

    public function test_quotes_parse()
    {
        $result = Entry_Parser::parse("FOO=\"BAR  \n\"");
        $this->check_positive_result($result, 'FOO', "BAR  \n");
    }

    public function test_newline_parse()
    {
        $result = Entry_Parser::parse('FOO="\n"');
        $this->check_positive_result($result, 'FOO', "\n");
    }

    public function test_tab_parse_double()
    {
        $result = Entry_Parser::parse('FOO="\t"');
        $this->check_positive_result($result, 'FOO', "\t");
    }

    public function test_tab_parse_single()
    {
        $result = Entry_Parser::parse('FOO=\'\t\'');
        $this->check_positive_result($result, 'FOO', '\t');
    }

    public function test_non_escape_parse1()
    {
        $result = Entry_Parser::parse('FOO=\n\v');
        $this->check_positive_result($result, 'FOO', '\n\v');
    }

    public function test_non_escape_parse2()
    {
        $result = Entry_Parser::parse('FOO=\q');
        $this->check_positive_result($result, 'FOO', '\q');
    }

    public function test_bad_escape_parse()
    {
        $result = Entry_Parser::parse('FOO="\q"');
        $this->check_error_result($result, 'Encountered an unexpected escape sequence at ["\q"].');
    }

    public function test_inline_variable()
    {
        $result = Entry_Parser::parse('FOO=$BAR');
        $this->check_positive_result($result, 'FOO', '$BAR', [0]);
    }

    public function test_inline_variable_offset()
    {
        $result = Entry_Parser::parse('FOO=AAA$BAR');
        $this->check_positive_result($result, 'FOO', 'AAA$BAR', [3]);
    }

    public function test_inline_variables()
    {
        $result = Entry_Parser::parse('FOO="TEST $BAR $$BAZ"');
        $this->check_positive_result($result, 'FOO', 'TEST $BAR $$BAZ', [11, 10, 5]);
    }

    public function test_non_inline_variable()
    {
        $result = Entry_Parser::parse('FOO=\'TEST $BAR $$BAZ\'');
        $this->check_positive_result($result, 'FOO', 'TEST $BAR $$BAZ');
        self::assertTrue($result->success()->isDefined());
    }

    public function test_whitespace_parse()
    {
        $result = Entry_Parser::parse("FOO=\"\n\"");
        $this->check_positive_result($result, 'FOO', "\n");
    }

    public function test_export_parse()
    {
        $result = Entry_Parser::parse('export FOO="bar baz"');
        $this->check_positive_result($result, 'FOO', 'bar baz');
    }

    public function test_export_parse_tab()
    {
        $result = Entry_Parser::parse("export\t\"FOO\"='bar baz'");
        $this->check_positive_result($result, 'FOO', 'bar baz');
    }

    public function test_export_parse_fail()
    {
        $result = Entry_Parser::parse('export "FOO="bar baz"');
        $this->check_error_result($result, 'Encountered an invalid name at ["FOO].');
    }

    public function test_closing_slash_parse()
    {
        $result = Entry_Parser::parse('SPVAR5="test some escaped characters like a quote \\" or maybe a backslash \\\\" # not escaped');
        $this->check_positive_result($result, 'SPVAR5', 'test some escaped characters like a quote " or maybe a backslash \\');
    }

    public function test_parse_invalid_spaces()
    {
        $result = Entry_Parser::parse('FOO=bar baz');
        $this->check_error_result($result, 'Encountered unexpected whitespace at [bar baz].');
    }

    public function test_parse_stray_equals()
    {
        $result = Entry_Parser::parse('=');
        $this->check_error_result($result, 'Encountered an unexpected equals at [=].');
    }

    public function test_parse_invalid_name()
    {
        $result = Entry_Parser::parse('FOO_ASD!=BAZ');
        $this->check_error_result($result, 'Encountered an invalid name at [FOO_ASD!].');
    }

    public function test_parser_escaping_double()
    {
        $result = Entry_Parser::parse('FOO_BAD="iiiiviiiixiiiiviiii\\a"');
        $this->check_error_result($result, 'Encountered an unexpected escape sequence at ["iiiiviiiixiiiiviiii\a"].');
    }

    public function test_parser_escaping_single()
    {
        $result = Entry_Parser::parse('FOO_BAD=\'iiiiviiiixiiiiviiii\\a\'');
        $this->check_positive_result($result, 'FOO_BAD', 'iiiiviiiixiiiiviiii\\a');
    }

    public function test_parser_missing_closing_single_quote()
    {
        $result = Entry_Parser::parse('TEST=\'erert');
        $this->check_error_result($result, 'Encountered a missing closing quote at [\'erert].');
    }

    public function test_parser_missing_closing_double_quote()
    {
        $result = Entry_Parser::parse('TEST="erert');
        $this->check_error_result($result, 'Encountered a missing closing quote at ["erert].');
    }

    public function test_parser_missing_closing_quotes()
    {
        $result = Entry_Parser::parse("TEST=\"erert\nTEST='erert\n");
        $this->check_error_result($result, 'Encountered a missing closing quote at ["erert].');
    }

    public function test_parser_closing_quote_with_escape()
    {
        $result = Entry_Parser::parse('TEST="\\');
        $this->check_error_result($result, 'Encountered a missing closing quote at ["\\].');
    }

    /**
     * @param \GrahamCampbell\ResultType\Result<\Dotenv\Parser\Entry,string> $result
     * @param string                                                         $name
     * @param string                                                         $chars
     * @param int[]                                                          $vars
     *
     * @return void
     */
    private function check_positive_result(Result $result, string $name, string $chars, array $vars = [])
    {
        self::assertTrue($result->success()->isDefined());

        $entry = $result->success()->get();
        self::assertInstanceOf(Entry::class, $entry);
        self::assertSame($name, $entry->get_name());
        self::assertTrue($entry->get_value()->isDefined());

        $value = $entry->get_value()->get();
        self::assertInstanceOf(Value::class, $value);
        self::assertSame($chars, $value->get_chars());
        self::assertSame($vars, $value->get_vars());
    }

    /**
     * @param \GrahamCampbell\ResultType\Result<\Dotenv\Parser\Entry,string> $result
     * @param string                                                         $name
     *
     * @return void
     */
    private function check_empty_result(Result $result, string $name)
    {
        self::assertTrue($result->success()->isDefined());

        $entry = $result->success()->get();
        self::assertInstanceOf(Entry::class, $entry);
        self::assertSame('FOO', $entry->get_name());
        self::assertFalse($entry->get_value()->isDefined());
    }

    /**
     * @param \GrahamCampbell\ResultType\Result<\Dotenv\Parser\Entry,string> $result
     * @param string                                                         $error
     *
     * @return void
     */
    private function check_error_result(Result $result, string $error)
    {
        self::assertTrue($result->error()->isDefined());
        self::assertSame($error, $result->error()->get());
    }
}
