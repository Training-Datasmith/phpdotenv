<?php

declare (strict_types=1);
namespace Dotenv\Parser;

interface Parser_Interface
{
    /**
     * Parse content into an entry array.
     *
     *
     * @throws \Dotenv\Exception\InvalidFileException
     * @return \Dotenv\Parser\Entry[]
     */
    public function parse(string $content);
}