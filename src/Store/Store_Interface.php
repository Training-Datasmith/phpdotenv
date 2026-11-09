<?php

declare (strict_types=1);
namespace Dotenv\Store;

interface Store_Interface
{
    /**
     * Read the content of the environment file(s).
     *
     * @throws \Dotenv\Exception\Invalid_Encoding_Exception|\Dotenv\Exception\Invalid_Path_Exception
     *
     * @return string
     */
    public function read();
}