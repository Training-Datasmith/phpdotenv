<?php

declare (strict_types=1);
namespace Dotenv\Repository\Adapter;

final class Immutable_Writer implements Writer_Interface
{
    /**
     * The inner writer to use.
     *
     * @var \Dotenv\Repository\Adapter\WriterInterface
     */
    private $writer;
    /**
     * The inner reader to use.
     *
     * @var \Dotenv\Repository\Adapter\ReaderInterface
     */
    private $reader;
    /**
     * The record of loaded variables.
     *
     * @var array<string, string>
     */
    private $loaded;
    /**
     * Variables that have been deleted. These cannot be re-written, preventing
     * a delete() → write() sequence from bypassing immutability.
     *
     * @var array<string, true>
     */
    private $deleted = [];
    /**
     * Create a new immutable writer instance.
     *
     *
     */
    public function __construct(Writer_Interface $writer, Reader_Interface $reader)
    {
        $this->writer = $writer;
        $this->reader = $reader;
        $this->loaded = [];
    }
    /**
     * Write to an environment variable, if possible.
     *
     * @param non-empty-string $name
     *
     */
    public function write(string $name, string $value): bool
    {
        // Don't overwrite existing environment variables
        // Ruby's dotenv does this with `ENV[key] ||= value`
        if ($this->is_externally_defined($name)) {
            return false;
        }
        // Don't allow re-writing a variable that was previously loaded and then deleted.
        // Without this guard, delete() followed by write() would bypass immutability.
        if (isset($this->deleted[$name])) {
            return false;
        }
        // Set the value on the inner writer
        if (!$this->writer->write($name, $value)) {
            return false;
        }
        // Record that we have loaded the variable
        $this->loaded[$name] = '';
        return true;
    }
    /**
     * Delete an environment variable, if possible.
     *
     * @param non-empty-string $name
     */
    public function delete(string $name): bool
    {
        // Don't clear existing environment variables
        if ($this->is_externally_defined($name)) {
            return false;
        }
        // Clear the value on the inner writer
        if (!$this->writer->delete($name)) {
            return false;
        }
        // Record the deletion so the variable cannot be re-written.
        // This prevents a delete() → write() sequence from bypassing immutability.
        $this->deleted[$name] = true;
        unset($this->loaded[$name]);
        return true;
    }
    /**
     * Determine if the given variable is externally defined.
     *
     * That is, is it an "existing" variable.
     *
     * @param non-empty-string $name
     */
    private function is_externally_defined(string $name): bool
    {
        return $this->reader->read($name)->is_defined() && !isset($this->loaded[$name]);
    }
}