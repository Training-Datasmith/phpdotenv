<?php

declare (strict_types=1);
namespace Dotenv\Parser;

use Dotenv\Util\Regex;
use Dotenv\Util\Str;
final class Lines
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
     * Process the array of lines of environment variables.
     *
     * This will produce an array of raw entries, one per variable.
     *
     * @param string[] $lines
     *
     * @return string[]
     */
    public static function process(array $lines): array
    {
        $output = [];
        $multiline = false;
        $multiline_buffer = [];
        foreach ($lines as $line) {
            [$multiline, $line, $multiline_buffer] = self::multiline_process($multiline, $line, $multiline_buffer);
            if (!$multiline && !self::is_comment_or_whitespace($line)) {
                $output[] = $line;
            }
        }
        return $output;
    }
    /**
     * Used to make all multiline variable process.
     *
     * @param string[] $buffer
     *
     * @return array{bool,string, string[]}
     */
    private static function multiline_process(bool $multiline, string $line, array $buffer): array
    {
        $starts_on_current_line = $multiline ? false : self::looks_like_multiline_start($line);
        // check if $line can be multiline variable
        if ($starts_on_current_line) {
            $multiline = true;
        }
        if ($multiline) {
            \array_push($buffer, $line);
            if (self::looks_like_multiline_stop($line, $starts_on_current_line)) {
                $multiline = false;
                $line = \implode("\n", $buffer);
                $buffer = [];
            }
        }
        return [$multiline, $line, $buffer];
    }
    /**
     * Determine if the given line can be the start of a multiline variable.
     *
     *
     * @return bool
     */
    private static function looks_like_multiline_start(string $line)
    {
        return Str::pos($line, '="')->map(static function () use ($line): bool {
            return self::looks_like_multiline_stop($line, true) === false;
        })->get_or_else(false);
    }
    /**
     * Determine if the given line can be the start of a multiline variable.
     *
     *
     * @return bool
     */
    private static function looks_like_multiline_stop(string $line, bool $started)
    {
        if ($line === '"') {
            return true;
        }
        return Regex::occurrences('/(?=([^\\\\]"))/', \str_replace('\\\\', '', $line))->map(static function (int $count) use ($started): bool {
            return $started ? $count > 1 : $count >= 1;
        })->success()->get_or_else(false);
    }
    /**
     * Determine if the line in the file is a comment or whitespace.
     *
     *
     */
    private static function is_comment_or_whitespace(string $line): bool
    {
        $line = \trim($line);
        return $line === '' || isset($line[0]) && $line[0] === '#';
    }
}