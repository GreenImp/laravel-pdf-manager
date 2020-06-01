<?php

namespace GreenImp\PdfManager\Exceptions;

use Exception;
use Illuminate\Support\Arr;

class InvalidMimeTypeException extends Exception
{
    public function __construct(string $mimeType = '', $allowedTypes = null)
    {
        $message = "Invalid mime type `{$mimeType}`";

        if (!empty($allowedTypes)) {
            $message .= '. One of "' . implode('", "', Arr::wrap($allowedTypes)) . '" expected';
        }

        parent::__construct($message);
    }
}
