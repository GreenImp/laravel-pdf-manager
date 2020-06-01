<?php

namespace GreenImp\PdfManager\Exceptions;

use Exception;

class InvalidFileException extends Exception
{
    public function __construct()
    {
        parent::__construct('File is invalid');
    }
}
