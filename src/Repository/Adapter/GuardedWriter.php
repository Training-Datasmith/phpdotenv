<?php

declare (strict_types=1);
namespace Dotenv\Repository\Adapter;

final class Guarded_Writer implements Writer_Interface
{
    /**
     * The inner writer to use.
     *
     * @var \Dotenv\Repository\Adapter\WriterInterface
     */
    private $writer;
    /**
     * The variable name allow list.
     *
     * @var string[]
     */
    private $allow_list;
    /**
     * Create a new guarded writer instance.
     *
     * @param string[]                                   $allowList
     *
     */
    public function __construct(Writer_Interface $writer, array $allow_list)
    {
        $this->writer = $writer;
        $this->allow_list = $allow_list;
    }
    /**
     * Write to an environment variable, if possible.
     *
     * @param non-empty-string $name
     *
     * @return bool
     */
    public function write(string $name, string $value)
    {
        // Don't set non-allowed variables
        if (!$this->is_allowed($name)) {
            return false;
        }
        // Set the value on the inner writer
        return $this->writer->write($name, $value);
    }
    /**
     * Delete an environment variable, if possible.
     *
     * @param non-empty-string $name
     *
     * @return bool
     */
    public function delete(string $name)
    {
        // Don't clear non-allowed variables
        if (!$this->is_allowed($name)) {
            return false;
        }
        // Set the value on the inner writer
        return $this->writer->delete($name);
    }
    /**
     * Determine if the given variable is allowed.
     *
     * @param non-empty-string $name
     */
    private function is_allowed(string $name): bool
    {
        return \in_array($name, $this->allow_list, true);
    }
}