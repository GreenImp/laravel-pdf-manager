<?php

namespace GreenImp\PdfManager\Exceptions;

use Exception;

class InvalidFieldException extends Exception
{
    public function __construct(string $fieldName)
    {
        parent::__construct('The field name "' . $fieldName . '" was not found in the document');
    }
}
