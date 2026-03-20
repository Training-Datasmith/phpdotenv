<?php

declare (strict_types=1);
namespace Dotenv\Repository;

use Dotenv\Repository\Adapter\Reader_Interface;
use Dotenv\Repository\Adapter\Writer_Interface;
use InvalidArgumentException;
final class Adapter_Repository implements Repository_Interface
{
    /**
     * The reader to use.
     *
     * @var \Dotenv\Repository\Adapter\ReaderInterface
     */
    private $reader;
    /**
     * The writer to use.
     *
     * @var \Dotenv\Repository\Adapter\WriterInterface
     */
    private $writer;
    /**
     * Create a new adapter repository instance.
     *
     *
     */
    public function __construct(Reader_Interface $reader, Writer_Interface $writer)
    {
        $this->reader = $reader;
        $this->writer = $writer;
    }
    /**
     * Determine if the given environment variable is defined.
     *
     *
     */
    public function has(string $name): bool
    {
        return '' !== $name && $this->reader->read($name)->is_defined();
    }
    /**
     * Get an environment variable.
     *
     *
     * @throws \InvalidArgumentException
     * @return string|null
     */
    public function get(string $name)
    {
        if ('' === $name) {
            throw new InvalidArgumentException('Expected name to be a non-empty string.');
        }
        return $this->reader->read($name)->get_or_else(null);
    }
    /**
     * Set an environment variable.
     *
     *
     * @throws \InvalidArgumentException
     *
     * @return bool
     */
    public function set(string $name, string $value)
    {
        if ('' === $name) {
            throw new InvalidArgumentException('Expected name to be a non-empty string.');
        }
        return $this->writer->write($name, $value);
    }
    /**
     * Clear an environment variable.
     *
     *
     * @throws \InvalidArgumentException
     * @return bool
     */
    public function clear(string $name)
    {
        if ('' === $name) {
            throw new InvalidArgumentException('Expected name to be a non-empty string.');
        }
        return $this->writer->delete($name);
    }
}