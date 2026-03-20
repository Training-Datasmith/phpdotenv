<?php

declare (strict_types=1);
namespace Dotenv\Parser;

use Php_Option\Option;
final class Entry
{
    /**
     * The entry name.
     *
     * @var string
     */
    private $name;
    /**
     * The entry value.
     *
     * @var \Dotenv\Parser\Value|null
     */
    private $value;
    /**
     * Create a new entry instance.
     *
     *
     */
    public function __construct(string $name, ?Value $value = null)
    {
        $this->name = $name;
        $this->value = $value;
    }
    /**
     * Get the entry name.
     *
     * @return string
     */
    public function get_name()
    {
        return $this->name;
    }
    /**
     * Get the entry value.
     *
     * @return \PhpOption\Option<\Dotenv\Parser\Value>
     */
    public function get_value()
    {
        /** @var \PhpOption\Option<\Dotenv\Parser\Value> */
        return Option::from_value($this->value);
    }
}