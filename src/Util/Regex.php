<?php

declare (strict_types=1);
namespace Dotenv\Util;

use Graham_Campbell\Result_Type\Error;
use Graham_Campbell\Result_Type\Success;
/**
 * @internal
 */
final class Regex
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
     * Perform a preg match, wrapping up the result.
     *
     *
     * @return \GrahamCampbell\ResultType\Result<bool, string>
     */
    public static function matches(string $pattern, string $subject)
    {
        return self::preg_and_wrap(static function (string $subject) use ($pattern): bool {
            return @\preg_match($pattern, $subject) === 1;
        }, $subject);
    }
    /**
     * Perform a preg match all, wrapping up the result.
     *
     *
     * @return \GrahamCampbell\ResultType\Result<int, string>
     */
    public static function occurrences(string $pattern, string $subject)
    {
        return self::preg_and_wrap(static function (string $subject) use ($pattern): int {
            return (int) @\preg_match_all($pattern, $subject);
        }, $subject);
    }
    /**
     * Perform a preg replace callback, wrapping up the result.
     *
     * @param callable(string[]): string $callback
     *
     * @return \GrahamCampbell\ResultType\Result<string, string>
     */
    public static function replace_callback(string $pattern, callable $callback, string $subject, ?int $limit = null)
    {
        return self::preg_and_wrap(static function (string $subject) use ($pattern, $callback, $limit) {
            return (string) @\preg_replace_callback($pattern, $callback, $subject, $limit ?? -1);
        }, $subject);
    }
    /**
     * Perform a preg split, wrapping up the result.
     *
     *
     * @return \GrahamCampbell\ResultType\Result<string[], string>
     */
    public static function split(string $pattern, string $subject)
    {
        return self::preg_and_wrap(static function (string $subject) use ($pattern): array {
            return (array) @\preg_split($pattern, $subject);
        }, $subject);
    }
    /**
     * Perform a preg operation, wrapping up the result.
     *
     * @template V
     *
     * @param callable(string): V $operation
     *
     * @return \GrahamCampbell\ResultType\Result<V, string>
     */
    private static function preg_and_wrap(callable $operation, string $subject)
    {
        $result = $operation($subject);
        if (\preg_last_error() !== \PREG_NO_ERROR) {
            /** @var \GrahamCampbell\ResultType\Result<V,string> */
            return Error::create(\preg_last_error_msg());
        }
        /** @var \GrahamCampbell\ResultType\Result<V,string> */
        return Success::create($result);
    }
}