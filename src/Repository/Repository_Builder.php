<?php

declare (strict_types=1);
namespace Dotenv\Repository;

use Dotenv\Repository\Adapter\Adapter_Interface;
use Dotenv\Repository\Adapter\Env_Const_Adapter;
use Dotenv\Repository\Adapter\Guarded_Writer;
use Dotenv\Repository\Adapter\Immutable_Writer;
use Dotenv\Repository\Adapter\Multi_Reader;
use Dotenv\Repository\Adapter\Multi_Writer;
use Dotenv\Repository\Adapter\Reader_Interface;
use Dotenv\Repository\Adapter\Server_Const_Adapter;
use Dotenv\Repository\Adapter\Writer_Interface;
use InvalidArgumentException;
use Php_Option\Some;
use ReflectionClass;
final class Repository_Builder
{
    /**
     * The set of default adapters.
     */
    private const DEFAULT_ADAPTERS = [Server_Const_Adapter::class, Env_Const_Adapter::class];
    /**
     * The set of readers to use.
     *
     * @var \Dotenv\Repository\Adapter\ReaderInterface[]
     */
    private $readers;
    /**
     * The set of writers to use.
     *
     * @var \Dotenv\Repository\Adapter\WriterInterface[]
     */
    private $writers;
    /**
     * Are we immutable?
     *
     * @var bool
     */
    private $immutable;
    /**
     * The variable name allow list.
     *
     * @var string[]|null
     */
    private $allow_list;
    /**
     * Create a new repository builder instance.
     *
     * @param \Dotenv\Repository\Adapter\ReaderInterface[] $readers
     * @param \Dotenv\Repository\Adapter\WriterInterface[] $writers
     * @param string[]|null                                $allowList
     *
     */
    private function __construct(array $readers = [], array $writers = [], bool $immutable = false, ?array $allow_list = null)
    {
        $this->readers = $readers;
        $this->writers = $writers;
        $this->immutable = $immutable;
        $this->allow_list = $allow_list;
    }
    /**
     * Create a new repository builder instance with no adapters added.
     */
    public static function create_with_no_adapters(): self
    {
        return new self();
    }
    /**
     * Create a new repository builder instance with the default adapters added.
     */
    public static function create_with_default_adapters(): self
    {
        $adapters = \iterator_to_array(self::default_adapters());
        return new self($adapters, $adapters);
    }
    /**
     * Return the array of default adapters.
     *
     * @return \Generator<\Dotenv\Repository\Adapter\AdapterInterface>
     */
    private static function default_adapters()
    {
        foreach (self::DEFAULT_ADAPTERS as $adapter) {
            $instance = $adapter::create();
            if ($instance->is_defined()) {
                yield $instance->get();
            }
        }
    }
    /**
     * Determine if the given name if of an adapterclass.
     *
     *
     * @return bool
     */
    private static function is_an_adapter_class(string $name)
    {
        if (!\class_exists($name)) {
            return false;
        }
        return (new ReflectionClass($name))->implements_interface(Adapter_Interface::class);
    }
    /**
     * Creates a repository builder with the given reader added.
     *
     * Accepts either a reader instance, or a class-string for an adapter. If
     * the adapter is not supported, then we silently skip adding it.
     *
     * @param \Dotenv\Repository\Adapter\ReaderInterface|string $reader
     *
     * @throws \InvalidArgumentException
     */
    public function add_reader($reader): self
    {
        if (!(\is_string($reader) && self::is_an_adapter_class($reader)) && !$reader instanceof Reader_Interface) {
            throw new InvalidArgumentException(\sprintf('Expected either an instance of %s or a class-string implementing %s', Reader_Interface::class, Adapter_Interface::class));
        }
        $optional = Some::create($reader)->flat_map(static function ($reader) {
            return \is_string($reader) ? $reader::create() : Some::create($reader);
        });
        $readers = \array_merge($this->readers, \iterator_to_array($optional));
        return new self($readers, $this->writers, $this->immutable, $this->allow_list);
    }
    /**
     * Creates a repository builder with the given writer added.
     *
     * Accepts either a writer instance, or a class-string for an adapter. If
     * the adapter is not supported, then we silently skip adding it.
     *
     * @param \Dotenv\Repository\Adapter\WriterInterface|string $writer
     *
     * @throws \InvalidArgumentException
     */
    public function add_writer($writer): self
    {
        if (!(\is_string($writer) && self::is_an_adapter_class($writer)) && !$writer instanceof Writer_Interface) {
            throw new InvalidArgumentException(\sprintf('Expected either an instance of %s or a class-string implementing %s', Writer_Interface::class, Adapter_Interface::class));
        }
        $optional = Some::create($writer)->flat_map(static function ($writer) {
            return \is_string($writer) ? $writer::create() : Some::create($writer);
        });
        $writers = \array_merge($this->writers, \iterator_to_array($optional));
        return new self($this->readers, $writers, $this->immutable, $this->allow_list);
    }
    /**
     * Creates a repository builder with the given adapter added.
     *
     * Accepts either an adapter instance, or a class-string for an adapter. If
     * the adapter is not supported, then we silently skip adding it. We will
     * add the adapter as both a reader and a writer.
     *
     * @param \Dotenv\Repository\Adapter\WriterInterface|string $adapter
     *
     * @throws \InvalidArgumentException
     */
    public function add_adapter($adapter): self
    {
        if (!(\is_string($adapter) && self::is_an_adapter_class($adapter)) && !$adapter instanceof Adapter_Interface) {
            throw new InvalidArgumentException(\sprintf('Expected either an instance of %s or a class-string implementing %s', Writer_Interface::class, Adapter_Interface::class));
        }
        $optional = Some::create($adapter)->flat_map(static function ($adapter) {
            return \is_string($adapter) ? $adapter::create() : Some::create($adapter);
        });
        $readers = \array_merge($this->readers, \iterator_to_array($optional));
        $writers = \array_merge($this->writers, \iterator_to_array($optional));
        return new self($readers, $writers, $this->immutable, $this->allow_list);
    }
    /**
     * Creates a repository builder with mutability enabled.
     */
    public function immutable(): self
    {
        return new self($this->readers, $this->writers, true, $this->allow_list);
    }
    /**
     * Creates a repository builder with the given allow list.
     *
     * @param string[]|null $allowList
     */
    public function allow_list(?array $allow_list = null): self
    {
        return new self($this->readers, $this->writers, $this->immutable, $allow_list);
    }
    /**
     * Creates a new repository instance.
     *
     * @return \Dotenv\Repository\RepositoryInterface
     */
    public function make(): \Dotenv\Repository\Adapter_Repository
    {
        $reader = new Multi_Reader($this->readers);
        $writer = new Multi_Writer($this->writers);
        if ($this->immutable) {
            $writer = new Immutable_Writer($writer, $reader);
        }
        if ($this->allow_list !== null) {
            $writer = new Guarded_Writer($writer, $this->allow_list);
        }
        return new Adapter_Repository($reader, $writer);
    }
}