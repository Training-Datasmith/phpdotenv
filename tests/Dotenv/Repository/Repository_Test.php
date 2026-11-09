<?php

declare(strict_types=1);

namespace Dotenv\Tests\Repository;

use Dotenv\Dotenv;
use Dotenv\Repository\Adapter\Array_Adapter;
use Dotenv\Repository\Repository_Builder;
use Dotenv\Repository\Repository_Interface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use TypeError;

final class Repository_Test extends TestCase
{
    /**
     * @var array<string, string>|null
     */
    private $key_val;

    /**
     * @before
     *
     * @return void
     */
    public function refresh_key_val()
    {
        $this->key_val(true);
    }

    /**
     * @return void
     */
    private function load()
    {
        Dotenv::create_mutable(\dirname(\dirname(__DIR__)).'/fixtures/env')->load();
    }

    /**
     * Generates a new key/value pair or returns the previous one.
     *
     * Since most of our functionality revolves around setting/retrieving keys
     * and values, we have this utility function to help generate new, unique
     * key/value pairs.
     *
     * @param bool $reset
     *
     * @return array<string, string>
     */
    private function key_val(bool $reset = false)
    {
        if (!isset($this->key_val) || $reset) {
            $this->key_val = [\uniqid() => \uniqid()];
        }

        return $this->key_val;
    }

    /**
     * Returns the key from key_val(), without reset.
     *
     * @return string
     */
    private function key()
    {
        $key_val = $this->key_val();

        return (string) \key($key_val);
    }

    /**
     * Returns the value from key_val(), without reset.
     *
     * @return string
     */
    private function value()
    {
        $key_val = $this->key_val();

        /** @var string */
        return \reset($key_val);
    }

    public function test_repository_instance_of()
    {
        self::assertInstanceOf(Repository_Interface::class, Repository_Builder::create_with_no_adapters()->make());
        self::assertInstanceOf(Repository_Interface::class, Repository_Builder::create_with_default_adapters()->make());
    }

    public function test_mutable_loader_clears_environment_vars()
    {
        $repository = Repository_Builder::create_with_default_adapters()->make();

        // Set an environment variable.
        $repository->set($this->key(), $this->value());

        // Clear the set environment variable.
        $repository->clear($this->key());
        self::assertNull($repository->get($this->key()));
        self::assertFalse(\getenv($this->key()));
        self::assertFalse(isset($_ENV[$this->key()]));
        self::assertFalse(isset($_SERVER[$this->key()]));
    }

    public function test_immutable_loader_cannot_clear_existing_environment_vars()
    {
        $this->load();

        $repository = Repository_Builder::create_with_default_adapters()->immutable()->make();

        // Pre-set an environment variable.
        Repository_Builder::create_with_default_adapters()->make()->set($this->key(), $this->value());

        // Attempt to clear the environment variable, check that it fails.
        $repository->clear($this->key());
        self::assertSame($this->value(), $repository->get($this->key()));
        self::assertTrue(isset($_ENV[$this->key()]));
        self::assertTrue(isset($_SERVER[$this->key()]));
    }

    public function test_immutable_loader_can_clear_set_environment_vars()
    {
        $this->load();

        $repository = Repository_Builder::create_with_default_adapters()->immutable()->make();

        // Set an environment variable.
        $repository->set($this->key(), $this->value());

        // Attempt to clear the environment variable, check that it works.
        $repository->clear($this->key());
        self::assertNull($repository->get($this->key()));
        self::assertFalse(\getenv($this->key()));
        self::assertFalse(isset($_ENV[$this->key()]));
        self::assertFalse(isset($_SERVER[$this->key()]));
    }

    public function test_checking_whether_variable_exists()
    {
        $this->load();

        $repo = Repository_Builder::create_with_default_adapters()->make();

        self::assertTrue($repo->has('FOO'));
        self::assertFalse($repo->has('NON_EXISTING_VARIABLE'));
    }

    public function test_has_with_bad_variable()
    {
        $repo = Repository_Builder::create_with_default_adapters()->make();

        $this->expectException(TypeError::class);

        $repo->has(null);
    }

    public function test_getting_variable_by_name()
    {
        $this->load();

        $repo = Repository_Builder::create_with_default_adapters()->make();

        self::assertSame('bar', $repo->get('FOO'));
    }

    public function test_getting_null_variable()
    {
        $repo = Repository_Builder::create_with_default_adapters()->make();

        $this->expectException(TypeError::class);

        $repo->get(null);
    }

    public function test_getting_empty_variable()
    {
        $repo = Repository_Builder::create_with_default_adapters()->make();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected name to be a non-empty string.');

        $repo->get('');
    }

    public function test_setting_variable()
    {
        $this->load();

        $repo = Repository_Builder::create_with_default_adapters()->make();

        self::assertSame('bar', $repo->get('FOO'));
        $repo->set('FOO', 'new');
        self::assertSame('new', $repo->get('FOO'));
    }

    public function test_setting_null_variable()
    {
        $repo = Repository_Builder::create_with_default_adapters()->make();

        $this->expectException(TypeError::class);

        $repo->set(null, 'foo');
    }

    public function test_setting_empty_variable()
    {
        $repo = Repository_Builder::create_with_default_adapters()->make();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected name to be a non-empty string.');

        $repo->set('', 'foo');
    }

    public function test_clearing_variable()
    {
        $this->load();

        $repo = Repository_Builder::create_with_default_adapters()->make();

        self::assertTrue($repo->has('FOO'));
        $repo->clear('FOO');
        self::assertFalse($repo->has('FOO'));
    }

    public function test_clearing_variable_with_array_adapter()
    {
        $adapter = Array_Adapter::create()->get();
        $repo = Repository_Builder::create_with_no_adapters()->add_reader($adapter)->add_writer($adapter)->make();

        self::assertFalse($repo->has('FOO'));
        $repo->set('FOO', 'BAR');
        self::assertTrue($repo->has('FOO'));
        $repo->clear('FOO');
        self::assertFalse($repo->has('FOO'));
    }

    public function test_clearing_null_variable()
    {
        $repo = Repository_Builder::create_with_default_adapters()->make();

        $this->expectException(TypeError::class);

        $repo->clear(null);
    }

    public function test_clearing_empty_variable()
    {
        $repo = Repository_Builder::create_with_default_adapters()->make();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected name to be a non-empty string.');

        $repo->clear('');
    }

    public function test_cannot_set_variable_on_immutable_instance()
    {
        $this->load();

        $repo = Repository_Builder::create_with_default_adapters()->immutable()->make();

        self::assertSame('bar', $repo->get('FOO'));

        $repo->set('FOO', 'new');

        self::assertSame('bar', $repo->get('FOO'));
    }

    public function test_cannot_clear_variable_on_immutable_instance()
    {
        $this->load();

        $repo = Repository_Builder::create_with_default_adapters()->immutable()->make();

        $repo->clear('FOO');

        self::assertTrue($repo->has('FOO'));
    }

    public function test_build_with_bad_reader()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected either an instance of ');

        Repository_Builder::create_with_no_adapters()->add_reader('123');
    }

    public function test_build_with_bad_writer()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected either an instance of ');

        Repository_Builder::create_with_no_adapters()->add_writer('123');
    }

    public function test_build_with_bad_adapter()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected either an instance of ');

        Repository_Builder::create_with_no_adapters()->add_adapter('');
    }
}
