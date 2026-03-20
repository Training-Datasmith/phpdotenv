<?php

declare (strict_types=1);
namespace Dotenv;

use Dotenv\Exception\Invalid_Path_Exception;
use Dotenv\Loader\Loader;
use Dotenv\Loader\Loader_Interface;
use Dotenv\Parser\Parser;
use Dotenv\Parser\Parser_Interface;
use Dotenv\Repository\Adapter\Array_Adapter;
use Dotenv\Repository\Adapter\Putenv_Adapter;
use Dotenv\Repository\Repository_Builder;
use Dotenv\Repository\Repository_Interface;
use Dotenv\Store\Store_Builder;
use Dotenv\Store\Store_Interface;
use Dotenv\Store\String_Store;
class Dotenv
{
    /**
     * The store instance.
     *
     * @var \Dotenv\Store\StoreInterface
     */
    private $store;
    /**
     * The parser instance.
     *
     * @var \Dotenv\Parser\ParserInterface
     */
    private $parser;
    /**
     * The loader instance.
     *
     * @var \Dotenv\Loader\LoaderInterface
     */
    private $loader;
    /**
     * The repository instance.
     *
     * @var \Dotenv\Repository\RepositoryInterface
     */
    private $repository;
    /**
     * Create a new dotenv instance.
     *
     *
     */
    public function __construct(Store_Interface $store, Parser_Interface $parser, Loader_Interface $loader, Repository_Interface $repository)
    {
        $this->store = $store;
        $this->parser = $parser;
        $this->loader = $loader;
        $this->repository = $repository;
    }
    /**
     * Create a new dotenv instance.
     *
     * @param string|string[]                        $paths
     * @param string|string[]|null                   $names
     *
     */
    public static function create(Repository_Interface $repository, $paths, $names = null, bool $short_circuit = true, ?string $file_encoding = null): self
    {
        $builder = $names === null ? Store_Builder::create_with_default_name() : Store_Builder::create_with_no_names();
        foreach ((array) $paths as $path) {
            $builder = $builder->add_path($path);
        }
        foreach ((array) $names as $name) {
            $builder = $builder->add_name($name);
        }
        if ($short_circuit) {
            $builder = $builder->short_circuit();
        }
        return new self($builder->file_encoding($file_encoding)->make(), new Parser(), new Loader(), $repository);
    }
    /**
     * Create a new mutable dotenv instance with default repository.
     *
     * @param string|string[]      $paths
     * @param string|string[]|null $names
     *
     * @return \Dotenv\Dotenv
     */
    public static function create_mutable($paths, $names = null, bool $short_circuit = true, ?string $file_encoding = null)
    {
        $repository = Repository_Builder::create_with_default_adapters()->make();
        return self::create($repository, $paths, $names, $short_circuit, $file_encoding);
    }
    /**
     * Create a new mutable dotenv instance with default repository with the putenv adapter.
     *
     * @param string|string[]      $paths
     * @param string|string[]|null $names
     *
     * @return \Dotenv\Dotenv
     */
    public static function create_unsafe_mutable($paths, $names = null, bool $short_circuit = true, ?string $file_encoding = null)
    {
        $repository = Repository_Builder::create_with_default_adapters()->add_adapter(Putenv_Adapter::class)->make();
        return self::create($repository, $paths, $names, $short_circuit, $file_encoding);
    }
    /**
     * Create a new immutable dotenv instance with default repository.
     *
     * @param string|string[]      $paths
     * @param string|string[]|null $names
     *
     * @return \Dotenv\Dotenv
     */
    public static function create_immutable($paths, $names = null, bool $short_circuit = true, ?string $file_encoding = null)
    {
        $repository = Repository_Builder::create_with_default_adapters()->immutable()->make();
        return self::create($repository, $paths, $names, $short_circuit, $file_encoding);
    }
    /**
     * Create a new immutable dotenv instance with default repository with the putenv adapter.
     *
     * @param string|string[]      $paths
     * @param string|string[]|null $names
     *
     * @return \Dotenv\Dotenv
     */
    public static function create_unsafe_immutable($paths, $names = null, bool $short_circuit = true, ?string $file_encoding = null)
    {
        $repository = Repository_Builder::create_with_default_adapters()->add_adapter(Putenv_Adapter::class)->immutable()->make();
        return self::create($repository, $paths, $names, $short_circuit, $file_encoding);
    }
    /**
     * Create a new dotenv instance with an array backed repository.
     *
     * @param string|string[]      $paths
     * @param string|string[]|null $names
     *
     * @return \Dotenv\Dotenv
     */
    public static function create_array_backed($paths, $names = null, bool $short_circuit = true, ?string $file_encoding = null)
    {
        $repository = Repository_Builder::create_with_no_adapters()->add_adapter(Array_Adapter::class)->make();
        return self::create($repository, $paths, $names, $short_circuit, $file_encoding);
    }
    /**
     * Parse the given content and resolve nested variables.
     *
     * This method behaves just like load(), only without mutating your actual
     * environment. We do this by using an array backed repository.
     *
     *
     * @throws \Dotenv\Exception\InvalidFileException
     * @return array<string, string|null>
     */
    public static function parse(string $content)
    {
        $repository = Repository_Builder::create_with_no_adapters()->add_adapter(Array_Adapter::class)->make();
        $phpdotenv = new self(new String_Store($content), new Parser(), new Loader(), $repository);
        return $phpdotenv->load();
    }
    /**
     * Read and load environment file(s).
     *
     * @throws \Dotenv\Exception\InvalidPathException|\Dotenv\Exception\InvalidEncodingException|\Dotenv\Exception\InvalidFileException
     *
     * @return array<string, string|null>
     */
    public function load()
    {
        $entries = $this->parser->parse($this->store->read());
        return $this->loader->load($this->repository, $entries);
    }
    /**
     * Read and load environment file(s), silently failing if no files can be read.
     *
     * @throws \Dotenv\Exception\InvalidEncodingException|\Dotenv\Exception\InvalidFileException
     *
     * @return array<string, string|null>
     */
    public function safe_load()
    {
        try {
            return $this->load();
        } catch (Invalid_Path_Exception $e) {
            // suppressing exception
            return [];
        }
    }
    /**
     * Required ensures that the specified variables exist, and returns a new validator object.
     *
     * @param string|string[] $variables
     *
     * @return \Dotenv\Validator
     */
    public function required($variables)
    {
        return (new Validator($this->repository, (array) $variables))->required();
    }
    /**
     * Returns a new validator object that won't check if the specified variables exist.
     *
     * @param string|string[] $variables
     */
    public function if_present($variables): \Dotenv\Validator
    {
        return new Validator($this->repository, (array) $variables);
    }
}