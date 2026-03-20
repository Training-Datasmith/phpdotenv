<?php

declare (strict_types=1);
namespace Dotenv\Store;

use Dotenv\Exception\Invalid_Path_Exception;
use Dotenv\Store\File\Reader;
final class File_Store implements Store_Interface
{
    /**
     * The file paths.
     *
     * @var string[]
     */
    private $file_paths;
    /**
     * Should file loading short circuit?
     *
     * @var bool
     */
    private $short_circuit;
    /**
     * The file encoding.
     *
     * @var string|null
     */
    private $file_encoding;
    /**
     * Create a new file store instance.
     *
     * @param string[]    $filePaths
     *
     */
    public function __construct(array $file_paths, bool $short_circuit, ?string $file_encoding = null)
    {
        $this->file_paths = $file_paths;
        $this->short_circuit = $short_circuit;
        $this->file_encoding = $file_encoding;
    }
    /**
     * Read the content of the environment file(s).
     *
     * @throws \Dotenv\Exception\InvalidEncodingException|\Dotenv\Exception\InvalidPathException
     */
    public function read(): string
    {
        if ($this->file_paths === []) {
            throw new Invalid_Path_Exception('At least one environment file path must be provided.');
        }
        $contents = Reader::read($this->file_paths, $this->short_circuit, $this->file_encoding);
        if (\count($contents) > 0) {
            return \implode("\n", $contents);
        }
        throw new Invalid_Path_Exception(\sprintf('Unable to read any of the environment file(s) at [%s].', \implode(', ', $this->file_paths)));
    }
}