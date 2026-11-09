<?php

declare(strict_types=1);

namespace Dotenv\Tests\Parser;

use Dotenv\Parser\Lexer;
use PHPUnit\Framework\TestCase;

final class Lexer_Test extends TestCase
{
    /**
     * @return array{string,string[]}[]
     */
    public static function provide_lex_cases()
    {
        return [
            ['', []],
            ['FOO', ['FOO']],
            ['FOO bar', ['FOO', ' ', 'bar']],
            ['FOO\\n()ab', ['FOO', '\\', 'n()ab']],
            ["FOO\n\n   A", ['FOO', "\n\n", '   ', 'A']],
            ['"VA=L"', ['"', 'VA=L', '"']],
            ['\' \'', ['\'', ' ', '\'']],
        ];
    }

    /**
     * @dataProvider provide_lex_cases
     *
     * @param string   $input
     * @param string[] $output
     *
     * @return void
     */
    public function test_lex(string $input, array $output)
    {
        self::assertSame($output, \iterator_to_array(Lexer::lex($input)));
    }
}
