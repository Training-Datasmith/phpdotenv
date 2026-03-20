<?php

declare (strict_types=1);
namespace Dotenv\Parser;

use Dotenv\Util\Regex;
use Dotenv\Util\Str;
use Graham_Campbell\Result_Type\Error;
use Graham_Campbell\Result_Type\Result;
use Graham_Campbell\Result_Type\Success;
final class Entry_Parser
{
    private const INITIAL_STATE = 0;
    private const UNQUOTED_STATE = 1;
    private const SINGLE_QUOTED_STATE = 2;
    private const DOUBLE_QUOTED_STATE = 3;
    private const ESCAPE_SEQUENCE_STATE = 4;
    private const WHITESPACE_STATE = 5;
    private const COMMENT_STATE = 6;
    private const REJECT_STATES = [self::SINGLE_QUOTED_STATE, self::DOUBLE_QUOTED_STATE, self::ESCAPE_SEQUENCE_STATE];
    /**
     * This class is a singleton.
     *
     * @codeCoverageIgnore
     */
    private function __construct()
    {
    }
    /**
     * Parse a raw entry into a proper entry.
     *
     * That is, turn a raw environment variable entry into a name and possibly
     * a value. We wrap the answer in a result type.
     *
     *
     * @return \GrahamCampbell\ResultType\Result<\Dotenv\Parser\Entry, string>
     */
    public static function parse(string $entry)
    {
        return self::split_string_into_parts($entry)->flat_map(static function (array $parts) {
            [$name, $value] = $parts;
            return self::parse_name($name)->flat_map(static function (string $name) use ($value) {
                /** @var Result<Value|null, string> */
                $parsed_value = $value === null ? Success::create(null) : self::parse_value($value);
                return $parsed_value->map(static function (?Value $value) use ($name): \Dotenv\Parser\Entry {
                    return new Entry($name, $value);
                });
            });
        });
    }
    /**
     * Split the compound string into parts.
     *
     *
     * @return \GrahamCampbell\ResultType\Result<array{string, string|null},string>
     */
    private static function split_string_into_parts(string $line)
    {
        /** @var array{string, string|null} */
        $result = Str::pos($line, '=')->map(static function () use ($line): array {
            return \array_map('trim', \explode('=', $line, 2));
        })->get_or_else([$line, null]);
        if ($result[0] === '') {
            /** @var \GrahamCampbell\ResultType\Result<array{string, string|null},string> */
            return Error::create(self::get_error_message('an unexpected equals', $line));
        }
        /** @var \GrahamCampbell\ResultType\Result<array{string, string|null},string> */
        return Success::create($result);
    }
    /**
     * Parse the given variable name.
     *
     * That is, strip the optional quotes and leading "export" from the
     * variable name. We wrap the answer in a result type.
     *
     *
     * @return \GrahamCampbell\ResultType\Result<string, string>
     */
    private static function parse_name(string $name)
    {
        if (Str::len($name) > 8 && Str::substr($name, 0, 6) === 'export' && \ctype_space(Str::substr($name, 6, 1))) {
            $name = \ltrim(Str::substr($name, 6));
        }
        if (self::is_quoted_name($name)) {
            $name = Str::substr($name, 1, -1);
        }
        if (!self::is_valid_name($name)) {
            /** @var \GrahamCampbell\ResultType\Result<string, string> */
            return Error::create(self::get_error_message('an invalid name', $name));
        }
        /** @var \GrahamCampbell\ResultType\Result<string, string> */
        return Success::create($name);
    }
    /**
     * Is the given variable name quoted?
     *
     *
     * @return bool
     */
    private static function is_quoted_name(string $name)
    {
        if (Str::len($name) < 3) {
            return false;
        }
        $first = Str::substr($name, 0, 1);
        $last = Str::substr($name, -1, 1);
        return $first === '"' && $last === '"' || $first === '\'' && $last === '\'';
    }
    /**
     * Is the given variable name valid?
     *
     *
     * @return bool
     */
    private static function is_valid_name(string $name)
    {
        return Regex::matches('~(*UTF8)\A[\p{Ll}\p{Lu}\p{M}\p{N}_.]+\z~', $name)->success()->get_or_else(false);
    }
    /**
     * Parse the given variable value.
     *
     * This has the effect of stripping quotes and comments, dealing with
     * special characters, and locating nested variables, but not resolving
     * them. Formally, we run a finite state automaton with an output tape: a
     * transducer. We wrap the answer in a result type.
     *
     *
     * @return \GrahamCampbell\ResultType\Result<\Dotenv\Parser\Value, string>
     */
    private static function parse_value(string $value)
    {
        if (\trim($value) === '') {
            /** @var \GrahamCampbell\ResultType\Result<\Dotenv\Parser\Value, string> */
            return Success::create(Value::blank());
        }
        return \array_reduce(\iterator_to_array(Lexer::lex($value)), static function (Result $data, string $token) {
            return $data->flat_map(static function (array $data) use ($token) {
                return self::process_token($data[1], $token)->map(static function (array $val) use ($data): array {
                    return [$data[0]->append($val[0], $val[1]), $val[2]];
                });
            });
        }, Success::create([Value::blank(), self::INITIAL_STATE]))->flat_map(static function (array $result) {
            if (in_array($result[1], self::REJECT_STATES, true)) {
                /** @var \GrahamCampbell\ResultType\Result<\Dotenv\Parser\Value, string> */
                return Error::create('a missing closing quote');
            }
            /** @var \GrahamCampbell\ResultType\Result<\Dotenv\Parser\Value, string> */
            return Success::create($result[0]);
        })->map_error(static function (string $err) use ($value) {
            return self::get_error_message($err, $value);
        });
    }
    /**
     * Process the given token.
     *
     *
     * @return \GrahamCampbell\ResultType\Result<array{string, bool, int}, string>
     */
    private static function process_token(int $state, string $token)
    {
        switch ($state) {
            case self::INITIAL_STATE:
                if ($token === '\'') {
                    /** @var \GrahamCampbell\ResultType\Result<array{string, bool, int}, string> */
                    return Success::create(['', false, self::SINGLE_QUOTED_STATE]);
                }
                if ($token === '"') {
                    /** @var \GrahamCampbell\ResultType\Result<array{string, bool, int}, string> */
                    return Success::create(['', false, self::DOUBLE_QUOTED_STATE]);
                }
                if ($token === '#') {
                    /** @var \GrahamCampbell\ResultType\Result<array{string, bool, int}, string> */
                    return Success::create(['', false, self::COMMENT_STATE]);
                }
                if ($token === '$') {
                    /** @var \GrahamCampbell\ResultType\Result<array{string, bool, int}, string> */
                    return Success::create([$token, true, self::UNQUOTED_STATE]);
                }
                /** @var \GrahamCampbell\ResultType\Result<array{string, bool, int}, string> */
                return Success::create([$token, false, self::UNQUOTED_STATE]);
            case self::UNQUOTED_STATE:
                if ($token === '#') {
                    /** @var \GrahamCampbell\ResultType\Result<array{string, bool, int}, string> */
                    return Success::create(['', false, self::COMMENT_STATE]);
                }
                if (\ctype_space($token)) {
                    /** @var \GrahamCampbell\ResultType\Result<array{string, bool, int}, string> */
                    return Success::create(['', false, self::WHITESPACE_STATE]);
                }
                if ($token === '$') {
                    /** @var \GrahamCampbell\ResultType\Result<array{string, bool, int}, string> */
                    return Success::create([$token, true, self::UNQUOTED_STATE]);
                }
                /** @var \GrahamCampbell\ResultType\Result<array{string, bool, int}, string> */
                return Success::create([$token, false, self::UNQUOTED_STATE]);
            case self::SINGLE_QUOTED_STATE:
                if ($token === '\'') {
                    /** @var \GrahamCampbell\ResultType\Result<array{string, bool, int}, string> */
                    return Success::create(['', false, self::WHITESPACE_STATE]);
                }
                /** @var \GrahamCampbell\ResultType\Result<array{string, bool, int}, string> */
                return Success::create([$token, false, self::SINGLE_QUOTED_STATE]);
            case self::DOUBLE_QUOTED_STATE:
                if ($token === '"') {
                    /** @var \GrahamCampbell\ResultType\Result<array{string, bool, int}, string> */
                    return Success::create(['', false, self::WHITESPACE_STATE]);
                }
                if ($token === '\\') {
                    /** @var \GrahamCampbell\ResultType\Result<array{string, bool, int}, string> */
                    return Success::create(['', false, self::ESCAPE_SEQUENCE_STATE]);
                }
                if ($token === '$') {
                    /** @var \GrahamCampbell\ResultType\Result<array{string, bool, int}, string> */
                    return Success::create([$token, true, self::DOUBLE_QUOTED_STATE]);
                }
                /** @var \GrahamCampbell\ResultType\Result<array{string, bool, int}, string> */
                return Success::create([$token, false, self::DOUBLE_QUOTED_STATE]);
            case self::ESCAPE_SEQUENCE_STATE:
                if ($token === '"' || $token === '\\') {
                    /** @var \GrahamCampbell\ResultType\Result<array{string, bool, int}, string> */
                    return Success::create([$token, false, self::DOUBLE_QUOTED_STATE]);
                }
                if ($token === '$') {
                    /** @var \GrahamCampbell\ResultType\Result<array{string, bool, int}, string> */
                    return Success::create([$token, false, self::DOUBLE_QUOTED_STATE]);
                }
                $first = Str::substr($token, 0, 1);
                if (\in_array($first, ['f', 'n', 'r', 't', 'v'], true)) {
                    /** @var \GrahamCampbell\ResultType\Result<array{string, bool, int}, string> */
                    return Success::create([\stripcslashes('\\' . $first) . Str::substr($token, 1), false, self::DOUBLE_QUOTED_STATE]);
                }
                /** @var \GrahamCampbell\ResultType\Result<array{string, bool, int}, string> */
                return Error::create('an unexpected escape sequence');
            case self::WHITESPACE_STATE:
                if ($token === '#') {
                    /** @var \GrahamCampbell\ResultType\Result<array{string, bool, int}, string> */
                    return Success::create(['', false, self::COMMENT_STATE]);
                }
                if (!\ctype_space($token)) {
                    /** @var \GrahamCampbell\ResultType\Result<array{string, bool, int}, string> */
                    return Error::create('unexpected whitespace');
                }
                /** @var \GrahamCampbell\ResultType\Result<array{string, bool, int}, string> */
                return Success::create(['', false, self::WHITESPACE_STATE]);
            case self::COMMENT_STATE:
                /** @var \GrahamCampbell\ResultType\Result<array{string, bool, int}, string> */
                return Success::create(['', false, self::COMMENT_STATE]);
            default:
                throw new \Error('Parser entered invalid state.');
        }
    }
    /**
     * Generate a friendly error message.
     *
     *
     */
    private static function get_error_message(string $cause, string $subject): string
    {
        return \sprintf('Encountered %s at [%s].', $cause, \strtok($subject, "\n"));
    }
}