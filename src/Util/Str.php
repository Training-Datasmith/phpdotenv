<?php

declare (strict_types=1);
namespace Dotenv\Util;

use Graham_Campbell\Result_Type\Error;
use Graham_Campbell\Result_Type\Success;
use Php_Option\Option;
/**
 * @internal
 */
final class Str
{
    /**
     * This class is a singleton.
     *
     * @codeCoverageIgnore
     */
    private function __construct()
    {
    }
    /**
     * Convert a string to UTF-8 from the given encoding.
     *
     *
     * @return \GrahamCampbell\ResultType\Result<string, string>
     */
    public static function utf8(string $input, ?string $encoding = null)
    {
        if ($encoding !== null && !\in_array($encoding, \mb_list_encodings(), true)) {
            /** @var \GrahamCampbell\ResultType\Result<string, string> */
            return Error::create(\sprintf('Illegal character encoding [%s] specified.', $encoding));
        }
        $converted = $encoding === null ? @\mb_convert_encoding($input, 'UTF-8') : @\mb_convert_encoding($input, 'UTF-8', $encoding);
        if (!is_string($converted)) {
            /** @var \GrahamCampbell\ResultType\Result<string, string> */
            return Error::create(\sprintf('Conversion from encoding [%s] failed.', $encoding ?? 'NULL'));
        }
        /**
         * this is for support UTF-8 with BOM encoding
         * @see https://en.wikipedia.org/wiki/Byte_order_mark
         * @see https://github.com/vlucas/phpdotenv/issues/500
         */
        if (str_starts_with($converted, "﻿")) {
            $converted = \substr($converted, 3);
        }
        /** @var \GrahamCampbell\ResultType\Result<string, string> */
        return Success::create($converted);
    }
    /**
     * Search for a given substring of the input.
     *
     *
     * @return \PhpOption\Option<int>
     */
    public static function pos(string $haystack, string $needle)
    {
        /** @var \PhpOption\Option<int> */
        return Option::from_value(\mb_strpos($haystack, $needle, 0, 'UTF-8'), false);
    }
    /**
     * Grab the specified substring of the input.
     *
     *
     */
    public static function substr(string $input, int $start, ?int $length = null): string
    {
        return \mb_substr($input, $start, $length, 'UTF-8');
    }
    /**
     * Compute the length of the given string.
     *
     *
     */
    public static function len(string $input): int
    {
        return \mb_strlen($input, 'UTF-8');
    }
}