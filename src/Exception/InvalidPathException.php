<?php

declare (strict_types=1);
namespace Dotenv\Exception;

use InvalidArgumentException;
final class Invalid_Path_Exception extends InvalidArgumentException implements Exception_Interface
{
}