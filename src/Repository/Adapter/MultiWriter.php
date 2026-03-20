<?php

declare (strict_types=1);
namespace Dotenv\Repository\Adapter;

final class Multi_Writer implements Writer_Interface
{
    /**
     * The set of writers to use.
     *
     * @var \Dotenv\Repository\Adapter\WriterInterface[]
     */
    private $writers;
    /**
     * Create a new multi-writer instance.
     *
     * @param \Dotenv\Repository\Adapter\WriterInterface[] $writers
     */
    public function __construct(array $writers)
    {
        $this->writers = $writers;
    }
    /**
     * Write to an environment variable, if possible.
     *
     * @param non-empty-string $name
     *
     */
    public function write(string $name, string $value): bool
    {
        foreach ($this->writers as $writers) {
            if (!$writers->write($name, $value)) {
                return false;
            }
        }
        return true;
    }
    /**
     * Delete an environment variable, if possible.
     *
     * @param non-empty-string $name
     */
    public function delete(string $name): bool
    {
        foreach ($this->writers as $writers) {
            if (!$writers->delete($name)) {
                return false;
            }
        }
        return true;
    }
}