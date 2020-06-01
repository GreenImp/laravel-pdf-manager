<?php

namespace GreenImp\PdfManager\Exceptions;

use Exception;

class FileSaveException extends Exception
{
    public function __construct(string $path)
    {
        parent::__construct('Error saving file "' . $path . '"');
    }
}
