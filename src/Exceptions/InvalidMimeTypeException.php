<?php

namespace GreenImp\PdfManager\Exceptions;

use Exception;
use Illuminate\Support\Arr;
use Throwable;

class InvalidMimeTypeException extends Exception
{
    /**
     * InvalidMimeTypeException constructor.
     *
     * @param  string  $mimeType  The invalid mime type
     * @param  array|null  $allowedTypes  [optional] The list of valid mime types
     * @param  Throwable|null  $previous  [optional] The previous throwable used for the exception chaining.
     */
    public function __construct(string $mimeType = '', array $allowedTypes = null, Throwable $previous = null)
    {
        $message = "Invalid mime type '{$mimeType}'";

        if (!empty($allowedTypes)) {
            $message .= '. One of "' . implode('", "', Arr::wrap($allowedTypes)) . '" expected';
        }

        parent::__construct($message, 0, $previous);
    }
}
