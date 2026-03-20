<?php

declare (strict_types=1);
namespace Dotenv\Store\File;

use Dotenv\Exception\Invalid_Encoding_Exception;
use Dotenv\Util\Str;
use Php_Option\Option;
/**
 * @internal
 */
final class Reader
{
    /**
     * This class is a singleton.
     *
     * @codeCoverageIgnore
     */
    private function __construct()
    {
    }
    /**
     * Read the file(s), and return their raw content.
     *
     * We provide the file path as the key, and its content as the value. If
     * short circuit mode is enabled, then the returned array with have length
     * at most one. File paths that couldn't be read are omitted entirely.
     *
     * @param string[]    $filePaths
     *
     * @throws \Dotenv\Exception\InvalidEncodingException
     *
     * @return array<string, string>
     */
    public static function read(array $file_paths, bool $short_circuit = true, ?string $file_encoding = null): array
    {
        $output = [];
        foreach ($file_paths as $file_path) {
            $content = self::read_from_file($file_path, $file_encoding);
            if ($content->is_defined()) {
                $output[$file_path] = $content->get();
                if ($short_circuit) {
                    break;
                }
            }
        }
        return $output;
    }
    /**
     * Read the given file.
     *
     *
     * @throws \Dotenv\Exception\InvalidEncodingException
     *
     * @return \PhpOption\Option<string>
     */
    private static function read_from_file(string $path, ?string $encoding = null)
    {
        /** @var Option<string> */
        $content = Option::from_value(@\file_get_contents($path), false);
        return $content->flat_map(static function (string $content) use ($encoding) {
            return Str::utf8($content, $encoding)->map_error(static function (string $error): void {
                throw new Invalid_Encoding_Exception($error);
            })->success();
        });
    }
}