<?php

declare (strict_types=1);
namespace Dotenv\Repository\Adapter;

use Php_Option\None;
use Php_Option\Option;
use Php_Option\Some;
final class Putenv_Adapter implements Adapter_Interface
{
    /**
     * Create a new putenv adapter instance.
     */
    private function __construct()
    {
    }
    /**
     * Create a new instance of the adapter, if it is available.
     *
     * @return \PhpOption\Option<\Dotenv\Repository\Adapter\AdapterInterface>
     */
    public static function create()
    {
        if (self::is_supported()) {
            /** @var \PhpOption\Option<AdapterInterface> */
            return Some::create(new self());
        }
        return None::create();
    }
    /**
     * Determines if the adapter is supported.
     */
    private static function is_supported(): bool
    {
        return \function_exists('getenv') && \function_exists('putenv');
    }
    /**
     * Read an environment variable, if it exists.
     *
     * @param non-empty-string $name
     *
     * @return \PhpOption\Option<string>
     */
    public function read(string $name)
    {
        /** @var \PhpOption\Option<string> */
        return Option::from_value(\getenv($name), false)->filter(static function ($value): bool {
            return \is_string($value);
        });
    }
    /**
     * Write to an environment variable, if possible.
     *
     * @param non-empty-string $name
     *
     */
    public function write(string $name, string $value): bool
    {
        \putenv("{$name}={$value}");
        return true;
    }
    /**
     * Delete an environment variable, if possible.
     *
     * @param non-empty-string $name
     */
    public function delete(string $name): bool
    {
        \putenv($name);
        return true;
    }
}