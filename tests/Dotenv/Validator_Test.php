<?php

declare(strict_types=1);

namespace Dotenv\Tests;

use Dotenv\Dotenv;
use Dotenv\Exception\Validation_Exception;
use Dotenv\Repository\Adapter\Array_Adapter;
use Dotenv\Repository\Repository_Builder;
use PHPUnit\Framework\TestCase;

final class Validator_Test extends TestCase
{
    /**
     * @var string
     */
    private static $folder;

    /**
     * @beforeClass
     *
     * @return void
     */
    public static function set_folder()
    {
        self::$folder = \dirname(__DIR__).'/fixtures/env';
    }

    /**
     * @param string $name
     *
     * @return array{\Dotenv\Repository\Repository_Interface,\Dotenv\Dotenv}
     */
    public static function create_array_dotenv(string $name = '.env')
    {
        $repository = Repository_Builder::create_with_no_adapters()->add_adapter(Array_Adapter::class)->make();

        return [$repository, Dotenv::create($repository, self::$folder, $name)];
    }

    /**
     * @doesNotPerformAssertions
     */
    public function test_dotenv_required_string_environment_vars()
    {
        $dotenv = self::create_array_dotenv()[1];
        $dotenv->load();
        $dotenv->required('FOO');
    }

    /**
     * @doesNotPerformAssertions
     */
    public function test_dotenv_allowed_values()
    {
        $dotenv = self::create_array_dotenv()[1];
        $dotenv->load();
        $dotenv->required('FOO')->allowed_values(['bar', 'baz']);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function test_dotenv_allowed_values_if_present()
    {
        $dotenv = self::create_array_dotenv()[1];
        $dotenv->load();
        $dotenv->if_present('FOO')->allowed_values(['bar', 'baz']);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function test_dotenv_allowed_values_if_not_present()
    {
        $dotenv = self::create_array_dotenv()[1];
        $dotenv->load();
        $dotenv->if_present('FOOQWERTYOOOOOO')->allowed_values(['bar', 'baz']);
    }

    public function test_dotenv_prohibited_values()
    {
        $dotenv = self::create_array_dotenv()[1];
        $dotenv->load();

        $this->expectException(Validation_Exception::class);
        $this->expectExceptionMessage('One or more environment variables failed assertions: FOO is not one of [buzz, buz].');

        $dotenv->required('FOO')->allowed_values(['buzz', 'buz']);
    }

    public function test_dotenv_prohibited_values_if_present()
    {
        $dotenv = self::create_array_dotenv()[1];
        $dotenv->load();

        $this->expectException(Validation_Exception::class);
        $this->expectExceptionMessage('One or more environment variables failed assertions: FOO is not one of [buzz, buz].');

        $dotenv->if_present('FOO')->allowed_values(['buzz', 'buz']);
    }

    public function test_dotenv_required_throws_runtime_exception()
    {
        [$repo, $dotenv] = self::create_array_dotenv();

        $dotenv->load();

        self::assertFalse($repo->has('FOOX'));
        self::assertFalse($repo->has('NOPE'));

        $this->expectException(Validation_Exception::class);
        $this->expectExceptionMessage('One or more environment variables failed assertions: FOOX is missing, NOPE is missing.');

        $dotenv->required(['FOOX', 'NOPE']);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function test_dotenv_required_array_environment_vars()
    {
        $dotenv = self::create_array_dotenv()[1];
        $dotenv->load();
        $dotenv->required(['FOO', 'BAR']);
    }

    public function test_dotenv_assertions()
    {
        [$repo, $dotenv] = self::create_array_dotenv('assertions.env');

        $dotenv->load();

        self::assertSame('val1', $repo->get('ASSERTVAR1'));
        self::assertSame('', $repo->get('ASSERTVAR2'));
        self::assertSame('val3   ', $repo->get('ASSERTVAR3'));
        self::assertSame('0', $repo->get('ASSERTVAR4'));
        self::assertSame('#foo', $repo->get('ASSERTVAR5'));
        self::assertSame("val1\nval2", $repo->get('ASSERTVAR6'));
        self::assertSame("\nval3", $repo->get('ASSERTVAR7'));
        self::assertSame("val3\n", $repo->get('ASSERTVAR8'));

        $dotenv->required([
            'ASSERTVAR1',
            'ASSERTVAR2',
            'ASSERTVAR3',
            'ASSERTVAR4',
            'ASSERTVAR5',
            'ASSERTVAR6',
            'ASSERTVAR7',
            'ASSERTVAR8',
            'ASSERTVAR9',
        ]);

        $dotenv->required([
            'ASSERTVAR1',
            'ASSERTVAR3',
            'ASSERTVAR4',
            'ASSERTVAR5',
            'ASSERTVAR6',
            'ASSERTVAR7',
            'ASSERTVAR8',
        ])->not_empty();

        $dotenv->required([
            'ASSERTVAR1',
            'ASSERTVAR4',
            'ASSERTVAR5',
        ])->not_empty()->allowed_values(['0', 'val1', '#foo']);
    }

    public function test_dotenv_empty_throws_runtime_exception()
    {
        $dotenv = self::create_array_dotenv('assertions.env')[1];
        $dotenv->load();

        $this->expectException(Validation_Exception::class);
        $this->expectExceptionMessage('One or more environment variables failed assertions: ASSERTVAR2 is empty.');

        $dotenv->required('ASSERTVAR2')->not_empty();
    }

    /**
     * @doesNotPerformAssertions
     */
    public function test_dotenv_empty_when_not_present()
    {
        $dotenv = self::create_array_dotenv('assertions.env')[1];
        $dotenv->load();
        $dotenv->if_present('ASSERTVAR2_NO_SUCH_VARIABLE')->not_empty();
    }

    public function test_dotenv_string_of_spaces_considered_empty()
    {
        $dotenv = self::create_array_dotenv('assertions.env')[1];
        $dotenv->load();

        $this->expectException(Validation_Exception::class);
        $this->expectExceptionMessage('One or more environment variables failed assertions: ASSERTVAR9 is empty.');

        $dotenv->required('ASSERTVAR9')->not_empty();
    }

    /**
     * List of valid boolean values in fixtures/env/booleans.env.
     *
     * @return string[][]
     */
    public static function valid_boolean_values_data_provider()
    {
        return [
            ['VALID_EXPLICIT_LOWERCASE_TRUE'],
            ['VALID_EXPLICIT_LOWERCASE_FALSE'],
            ['VALID_EXPLICIT_UPPERCASE_TRUE'],
            ['VALID_EXPLICIT_UPPERCASE_FALSE'],
            ['VALID_EXPLICIT_MIXEDCASE_TRUE'],
            ['VALID_EXPLICIT_MIXEDCASE_FALSE'],

            ['VALID_NUMBER_TRUE'],
            ['VALID_NUMBER_FALSE'],

            ['VALID_ONOFF_LOWERCASE_TRUE'],
            ['VALID_ONOFF_LOWERCASE_FALSE'],
            ['VALID_ONOFF_UPPERCASE_TRUE'],
            ['VALID_ONOFF_UPPERCASE_FALSE'],
            ['VALID_ONOFF_MIXEDCASE_TRUE'],
            ['VALID_ONOFF_MIXEDCASE_FALSE'],

            ['VALID_YESNO_LOWERCASE_TRUE'],
            ['VALID_YESNO_LOWERCASE_FALSE'],
            ['VALID_YESNO_UPPERCASE_TRUE'],
            ['VALID_YESNO_UPPERCASE_FALSE'],
            ['VALID_YESNO_MIXEDCASE_TRUE'],
            ['VALID_YESNO_MIXEDCASE_FALSE'],
        ];
    }

    /**
     * @dataProvider valid_boolean_values_data_provider
     * @doesNotPerformAssertions
     */
    public function test_can_validate_booleans(string $boolean)
    {
        $dotenv = Dotenv::create_immutable(self::$folder, 'booleans.env');
        $dotenv->load();
        $dotenv->required($boolean)->is_boolean();
    }

    /**
     * @dataProvider valid_boolean_values_data_provider
     * @doesNotPerformAssertions
     */
    public function test_can_validate_booleans_if_present(string $boolean)
    {
        $dotenv = Dotenv::create_immutable(self::$folder, 'booleans.env');
        $dotenv->load();
        $dotenv->if_present($boolean)->is_boolean();
    }

    /**
     * List of non-boolean values in fixtures/env/booleans.env.
     *
     * @return string[][]
     */
    public static function invalid_boolean_values_data_provider()
    {
        return [
            ['INVALID_SOMETHING'],
            ['INVALID_EMPTY'],
            ['INVALID_EMPTY_STRING'],
            ['INVALID_NULL'],
            ['INVALID_NUMBER_POSITIVE'],
            ['INVALID_NUMBER_NEGATIVE'],
            ['INVALID_MINUS'],
            ['INVALID_TILDA'],
            ['INVALID_EXCLAMATION'],
        ];
    }

    /**
     * @dataProvider invalid_boolean_values_data_provider
     */
    public function test_can_invalidate_non_booleans(string $boolean)
    {
        $dotenv = Dotenv::create_immutable(self::$folder, 'booleans.env');
        $dotenv->load();

        $this->expectException(Validation_Exception::class);
        $this->expectExceptionMessage('One or more environment variables failed assertions: INVALID_');

        $dotenv->required($boolean)->is_boolean();
    }

    /**
     * @dataProvider invalid_boolean_values_data_provider
     */
    public function test_can_invalidate_non_booleans_if_present(string $boolean)
    {
        $dotenv = Dotenv::create_immutable(self::$folder, 'booleans.env');
        $dotenv->load();

        $this->expectException(Validation_Exception::class);
        $this->expectExceptionMessage('One or more environment variables failed assertions: INVALID_');

        $dotenv->if_present($boolean)->is_boolean();
    }

    public function test_can_invalidate_boolean_non_exist()
    {
        $dotenv = Dotenv::create_immutable(self::$folder, 'booleans.env');
        $dotenv->load();

        $this->expectException(Validation_Exception::class);
        $this->expectExceptionMessage('One or more environment variables failed assertions: VAR_DOES_NOT_EXIST_234782462764');

        $dotenv->required(['VAR_DOES_NOT_EXIST_234782462764'])->is_boolean();
    }

    /**
     * @doesNotPerformAssertions
     */
    public function test_if_present_boolean_non_exist()
    {
        $dotenv = Dotenv::create_immutable(self::$folder, 'booleans.env');
        $dotenv->load();
        $dotenv->if_present(['VAR_DOES_NOT_EXIST_234782462764'])->is_boolean();
    }

    /**
     * List of valid integer values in fixtures/env/integers.env.
     *
     * @return string[][]
     */
    public static function valid_integer_values_data_provider()
    {
        return [
            ['VALID_ZERO'],
            ['VALID_ONE'],
            ['VALID_TWO'],
            ['VALID_LARGE'],
            ['VALID_HUGE'],
        ];
    }

    /**
     * @dataProvider valid_integer_values_data_provider
     * @doesNotPerformAssertions
     */
    public function test_can_validate_integers(string $integer)
    {
        $dotenv = Dotenv::create_immutable(self::$folder, 'integers.env');
        $dotenv->load();
        $dotenv->required($integer)->is_integer();
    }

    /**
     * @dataProvider valid_integer_values_data_provider
     * @doesNotPerformAssertions
     */
    public function test_can_validate_integers_if_present(string $integer)
    {
        $dotenv = Dotenv::create_immutable(self::$folder, 'integers.env');
        $dotenv->load();
        $dotenv->if_present($integer)->is_integer();
    }

    /**
     * List of non-integer values in fixtures/env/integers.env.
     *
     * @return string[][]
     */
    public static function invalid_integer_values_data_provider()
    {
        return [
            ['INVALID_SOMETHING'],
            ['INVALID_EMPTY'],
            ['INVALID_EMPTY_STRING'],
            ['INVALID_NULL'],
            ['INVALID_NEGATIVE'],
            ['INVALID_MINUS'],
            ['INVALID_TILDA'],
            ['INVALID_EXCLAMATION'],
            ['INVALID_SPACES'],
            ['INVALID_COMMAS'],
        ];
    }

    /**
     * @dataProvider invalid_integer_values_data_provider
     */
    public function test_can_invalidate_non_integers(string $integer)
    {
        $dotenv = Dotenv::create_immutable(self::$folder, 'integers.env');
        $dotenv->load();

        $this->expectException(Validation_Exception::class);
        $this->expectExceptionMessage('One or more environment variables failed assertions: INVALID_');

        $dotenv->required($integer)->is_integer();
    }

    /**
     * @dataProvider invalid_integer_values_data_provider
     */
    public function test_can_invalidate_non_integers_if_exist(string $integer)
    {
        $dotenv = Dotenv::create_immutable(self::$folder, 'integers.env');
        $dotenv->load();

        $this->expectException(Validation_Exception::class);
        $this->expectExceptionMessage('One or more environment variables failed assertions: INVALID_');

        $dotenv->if_present($integer)->is_integer();
    }

    public function test_can_invalidate_integer_non_exist()
    {
        $dotenv = Dotenv::create_immutable(self::$folder, 'integers.env');
        $dotenv->load();

        $this->expectException(Validation_Exception::class);
        $this->expectExceptionMessage('One or more environment variables failed assertions: VAR_DOES_NOT_EXIST_234782462764');

        $dotenv->required(['VAR_DOES_NOT_EXIST_234782462764'])->is_integer();
    }

    /**
     * @doesNotPerformAssertions
     */
    public function test_if_present_integer_non_exist()
    {
        $dotenv = Dotenv::create_immutable(self::$folder, 'integers.env');
        $dotenv->load();
        $dotenv->if_present(['VAR_DOES_NOT_EXIST_234782462764'])->is_integer();
    }

    /**
     * @doesNotPerformAssertions
     */
    public function test_dotenv_regex_match_pass()
    {
        $dotenv = Dotenv::create_immutable(self::$folder);
        $dotenv->load();
        $dotenv->required('FOO')->allowed_regex_values('([[:lower:]]{3})');
    }

    public function test_dotenv_regex_match_fail()
    {
        $dotenv = Dotenv::create_immutable(self::$folder);
        $dotenv->load();

        $this->expectException(Validation_Exception::class);
        $this->expectExceptionMessage('One or more environment variables failed assertions: FOO does not match "/^([[:lower:]]{1})$/".');

        $dotenv->required('FOO')->allowed_regex_values('/^([[:lower:]]{1})$/');
    }

    public function test_dotenv_regex_match_error()
    {
        $dotenv = Dotenv::create_immutable(self::$folder);
        $dotenv->load();

        $this->expectException(Validation_Exception::class);
        $this->expectExceptionMessage('One or more environment variables failed assertions: FOO does not match "/([[:lower:]{1{".');

        $dotenv->required('FOO')->allowed_regex_values('/([[:lower:]{1{');
    }

    /**
     * @doesNotPerformAssertions
     */
    public function test_dotenv_regex_match_not_present()
    {
        $dotenv = Dotenv::create_immutable(self::$folder);
        $dotenv->load();
        $dotenv->if_present('FOOOOOOOOOOO')->allowed_regex_values('([[:lower:]]{3})');
    }
}
