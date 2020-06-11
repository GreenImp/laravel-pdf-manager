<?php

namespace GreenImp\PdfManager\Exceptions;

use Exception;
use Throwable;

class InvalidFileException extends Exception
{
    /**
     * InvalidFileException constructor.
     *
     * @param  string  $path  The path of the invalid file
     * @param  Throwable|null  $previous  [optional] The previous throwable used for the exception chaining.
     */
    public function __construct(string $path, Throwable $previous = null)
    {
        parent::__construct('File is invalid: ' . $path, 0, $previous);
    }
}
