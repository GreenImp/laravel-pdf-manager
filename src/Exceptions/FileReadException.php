<?php

namespace GreenImp\PdfManager\Exceptions;

use Exception;
use Throwable;

class FileReadException extends Exception
{
    /**
     * FileReadException constructor.
     *
     * @param  string  $path  The file path that failed to be read
     * @param  Throwable|null  $previous  [optional] The previous throwable used for the exception chaining.
     */
    public function __construct(string $path, Throwable $previous = null)
    {
        parent::__construct('Error reading file: ' . $path, 0, $previous);
    }
}
