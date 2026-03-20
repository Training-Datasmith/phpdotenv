<?php

declare (strict_types=1);
namespace Dotenv\Parser;

use Dotenv\Exception\Invalid_File_Exception;
use Dotenv\Util\Regex;
use Graham_Campbell\Result_Type\Result;
use Graham_Campbell\Result_Type\Success;
final class Parser implements Parser_Interface
{
    /**
     * Parse content into an entry array.
     *
     *
     * @throws \Dotenv\Exception\InvalidFileException
     * @return \Dotenv\Parser\Entry[]
     */
    public function parse(string $content)
    {
        return Regex::split("/(\r\n|\n|\r)/", $content)->map_error(static function (): string {
            return 'Could not split into separate lines.';
        })->flat_map(static function (array $lines) {
            return self::process(Lines::process($lines));
        })->map_error(static function (string $error): void {
            throw new Invalid_File_Exception(\sprintf('Failed to parse dotenv file. %s', $error));
        })->success()->get();
    }
    /**
     * Convert the raw entries into proper entries.
     *
     * @param string[] $entries
     *
     * @return \GrahamCampbell\ResultType\Result<\Dotenv\Parser\Entry[], string>
     */
    private static function process(array $entries): \Graham_Campbell\Result_Type\Result
    {
        /** @var \GrahamCampbell\ResultType\Result<\Dotenv\Parser\Entry[], string> */
        return \array_reduce($entries, static function (Result $result, string $raw) {
            return $result->flat_map(static function (array $entries) use ($raw) {
                return Entry_Parser::parse($raw)->map(static function (Entry $entry) use ($entries): array {
                    /** @var \Dotenv\Parser\Entry[] */
                    return \array_merge($entries, [$entry]);
                });
            });
        }, Success::create([]));
    }
}