<?php

namespace GreenImp\PdfManager\Exceptions;

use Exception;
use Throwable;

class FileSaveException extends Exception
{
    /**
     * FileSaveException constructor.
     *
     * @param  string  $path  The file path that failed to save
     * @param  Throwable|null  $previous  [optional] The previous throwable used for the exception chaining.
     */
    public function __construct(string $path, Throwable $previous = null)
    {
        parent::__construct('Error saving file "' . $path . '"', 0, $previous);
    }
}
