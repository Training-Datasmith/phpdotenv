<?php

declare (strict_types=1);
namespace Dotenv\Repository\Adapter;

interface Writer_Interface
{
    /**
     * Write to an environment variable, if possible.
     *
     * @param non-empty-string $name
     *
     * @return bool
     */
    public function write(string $name, string $value);
    /**
     * Delete an environment variable, if possible.
     *
     * @param non-empty-string $name
     *
     * @return bool
     */
    public function delete(string $name);
}