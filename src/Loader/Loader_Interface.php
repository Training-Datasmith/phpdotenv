<?php

declare (strict_types=1);
namespace Dotenv\Loader;

use Dotenv\Repository\Repository_Interface;
interface Loader_Interface
{
    /**
     * Load the given entries into the repository.
     *
     * @param \Dotenv\Parser\Entry[]                 $entries
     * @return array<string, string|null>
     */
    public function load(Repository_Interface $repository, array $entries);
}