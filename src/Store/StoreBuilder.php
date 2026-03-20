<?php

declare (strict_types=1);
namespace Dotenv\Store;

use Dotenv\Store\File\Paths;
final class Store_Builder
{
    /**
     * The of default name.
     */
    private const DEFAULT_NAME = '.env';
    /**
     * The paths to search within.
     *
     * @var string[]
     */
    private $paths;
    /**
     * The file names to search for.
     *
     * @var string[]
     */
    private $names;
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
     * Create a new store builder instance.
     *
     * @param string[]    $paths
     * @param string[]    $names
     *
     */
    private function __construct(array $paths = [], array $names = [], bool $short_circuit = false, ?string $file_encoding = null)
    {
        $this->paths = $paths;
        $this->names = $names;
        $this->short_circuit = $short_circuit;
        $this->file_encoding = $file_encoding;
    }
    /**
     * Create a new store builder instance with no names.
     */
    public static function create_with_no_names(): self
    {
        return new self();
    }
    /**
     * Create a new store builder instance with the default name.
     */
    public static function create_with_default_name(): self
    {
        return new self([], [self::DEFAULT_NAME]);
    }
    /**
     * Creates a store builder with the given path added.
     *
     *
     */
    public function add_path(string $path): self
    {
        return new self(\array_merge($this->paths, [$path]), $this->names, $this->short_circuit, $this->file_encoding);
    }
    /**
     * Creates a store builder with the given name added.
     *
     *
     */
    public function add_name(string $name): self
    {
        return new self($this->paths, \array_merge($this->names, [$name]), $this->short_circuit, $this->file_encoding);
    }
    /**
     * Creates a store builder with short circuit mode enabled.
     */
    public function short_circuit(): self
    {
        return new self($this->paths, $this->names, true, $this->file_encoding);
    }
    /**
     * Creates a store builder with the specified file encoding.
     *
     *
     */
    public function file_encoding(?string $file_encoding = null): self
    {
        return new self($this->paths, $this->names, $this->short_circuit, $file_encoding);
    }
    /**
     * Creates a new store instance.
     *
     * @return \Dotenv\Store\StoreInterface
     */
    public function make(): \Dotenv\Store\File_Store
    {
        return new File_Store(Paths::file_paths($this->paths, $this->names), $this->short_circuit, $this->file_encoding);
    }
}