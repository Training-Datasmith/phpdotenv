<?php

declare (strict_types=1);
namespace Dotenv\Loader;

use Dotenv\Parser\Value;
use Dotenv\Repository\Repository_Interface;
use Dotenv\Util\Regex;
use Dotenv\Util\Str;
use Php_Option\Option;
final class Resolver
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
     * Resolve the nested variables in the given value.
     *
     * Replaces ${varname} patterns in the allowed positions in the variable
     * value by an existing environment variable.
     *
     *
     */
    public static function resolve(Repository_Interface $repository, Value $value): string
    {
        return \array_reduce($value->get_vars(), static function (string $s, int $i) use ($repository): string {
            return Str::substr($s, 0, $i) . self::resolve_variable($repository, Str::substr($s, $i));
        }, $value->get_chars());
    }
    /**
     * Resolve a single nested variable.
     *
     *
     * @return string
     */
    private static function resolve_variable(Repository_Interface $repository, string $str)
    {
        return Regex::replace_callback('/\A\${([a-zA-Z0-9_.]+)}/', static function (array $matches) use ($repository) {
            /** @var string */
            return Option::from_value($repository->get($matches[1]))->get_or_else($matches[0]);
        }, $str, 1)->success()->get_or_else($str);
    }
}