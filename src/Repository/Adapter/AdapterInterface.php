<?php

declare (strict_types=1);
namespace Dotenv\Repository\Adapter;

interface Adapter_Interface extends Reader_Interface, Writer_Interface
{
    /**
     * Create a new instance of the adapter, if it is available.
     *
     * @return \PhpOption\Option<\Dotenv\Repository\Adapter\AdapterInterface>
     */
    public static function create();
}