<?php

namespace GreenImp\PdfManager\Exceptions;

use Exception;
use Throwable;

class InvalidFieldException extends Exception
{
    /**
     * InvalidFieldException constructor.
     *
     * @param  string  $fieldName  The name of the invalid field
     * @param  Throwable|null  $previous  [optional] The previous throwable used for the exception chaining.
     */
    public function __construct(string $fieldName, Throwable $previous = null)
    {
        parent::__construct('The field name "' . $fieldName . '" was not found in the document', 0, $previous);
    }
}
