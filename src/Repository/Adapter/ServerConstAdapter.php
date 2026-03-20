<?php

declare (strict_types=1);
namespace Dotenv\Repository\Adapter;

use Php_Option\Option;
use Php_Option\Some;
final class Server_Const_Adapter implements Adapter_Interface
{
    /**
     * Create a new server const adapter instance.
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
        /** @var \PhpOption\Option<AdapterInterface> */
        return Some::create(new self());
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
        return Option::from_arrays_value($_SERVER, $name)->filter(static function ($value): bool {
            return \is_scalar($value);
        })->map(static function ($value): string {
            if ($value === false) {
                return 'false';
            }
            if ($value === true) {
                return 'true';
            }
            return (string) $value;
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
        $_SERVER[$name] = $value;
        return true;
    }
    /**
     * Delete an environment variable, if possible.
     *
     * @param non-empty-string $name
     */
    public function delete(string $name): bool
    {
        unset($_SERVER[$name]);
        return true;
    }
}