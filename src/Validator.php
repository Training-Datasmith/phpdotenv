<?php

declare (strict_types=1);
namespace Dotenv;

use Dotenv\Exception\Validation_Exception;
use Dotenv\Repository\Repository_Interface;
use Dotenv\Util\Regex;
use Dotenv\Util\Str;
class Validator
{
    /**
     * The environment repository instance.
     *
     * @var \Dotenv\Repository\RepositoryInterface
     */
    private $repository;
    /**
     * The variables to validate.
     *
     * @var string[]
     */
    private $variables;
    /**
     * Create a new validator instance.
     *
     * @param string[]                               $variables
     *
     */
    public function __construct(Repository_Interface $repository, array $variables)
    {
        $this->repository = $repository;
        $this->variables = $variables;
    }
    /**
     * Assert that each variable is present.
     *
     * @throws \Dotenv\Exception\ValidationException
     *
     * @return \Dotenv\Validator
     */
    public function required()
    {
        return $this->assert(static function (?string $value): bool {
            return $value !== null;
        }, 'is missing');
    }
    /**
     * Assert that each variable is not empty.
     *
     * @throws \Dotenv\Exception\ValidationException
     *
     * @return \Dotenv\Validator
     */
    public function not_empty()
    {
        return $this->assert_nullable(static function (string $value): bool {
            return Str::len(\trim($value)) > 0;
        }, 'is empty');
    }
    /**
     * Assert that each specified variable is an integer.
     *
     * @throws \Dotenv\Exception\ValidationException
     *
     * @return \Dotenv\Validator
     */
    public function is_integer()
    {
        return $this->assert_nullable(static function (string $value): bool {
            return \ctype_digit($value);
        }, 'is not an integer');
    }
    /**
     * Assert that each specified variable is a boolean.
     *
     * @throws \Dotenv\Exception\ValidationException
     *
     * @return \Dotenv\Validator
     */
    public function is_boolean()
    {
        return $this->assert_nullable(static function (string $value): bool {
            if ($value === '') {
                return false;
            }
            return \filter_var($value, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE) !== null;
        }, 'is not a boolean');
    }
    /**
     * Assert that each variable is amongst the given choices.
     *
     * @param string[] $choices
     *
     * @throws \Dotenv\Exception\ValidationException
     *
     * @return \Dotenv\Validator
     */
    public function allowed_values(array $choices)
    {
        return $this->assert_nullable(static function (string $value) use ($choices): bool {
            return \in_array($value, $choices, true);
        }, \sprintf('is not one of [%s]', \implode(', ', $choices)));
    }
    /**
     * Assert that each variable matches the given regular expression.
     *
     *
     * @throws \Dotenv\Exception\ValidationException
     * @return \Dotenv\Validator
     */
    public function allowed_regex_values(string $regex)
    {
        return $this->assert_nullable(static function (string $value) use ($regex) {
            return Regex::matches($regex, $value)->success()->get_or_else(false);
        }, \sprintf('does not match "%s"', $regex));
    }
    /**
     * Assert that the callback returns true for each variable.
     *
     * @param callable(?string):bool $callback
     *
     * @throws \Dotenv\Exception\ValidationException
     *
     */
    public function assert(callable $callback, string $message): self
    {
        $failing = [];
        foreach ($this->variables as $variable) {
            if ($callback($this->repository->get($variable)) === false) {
                $failing[] = \sprintf('%s %s', $variable, $message);
            }
        }
        if (\count($failing) > 0) {
            throw new Validation_Exception(\sprintf('One or more environment variables failed assertions: %s.', \implode(', ', $failing)));
        }
        return $this;
    }
    /**
     * Assert that the callback returns true for each variable.
     *
     * Skip checking null variable values.
     *
     * @param callable(string):bool $callback
     *
     * @throws \Dotenv\Exception\ValidationException
     * @return \Dotenv\Validator
     */
    public function assert_nullable(callable $callback, string $message)
    {
        return $this->assert(static function (?string $value) use ($callback) {
            if ($value === null) {
                return true;
            }
            return $callback($value);
        }, $message);
    }
}