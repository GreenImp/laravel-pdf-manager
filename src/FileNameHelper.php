<?php

namespace GreenImp\PdfManager;

use Carbon\Carbon;
use Illuminate\Support\Str;

class FileNameHelper
{
    /**
     * Generates a unique filename based on the current time and a unique ID.
     * It uses the time for a human readable reference, and a UUID to ensure uniqueness.
     *
     * The format is:
     * ```
     * ({$prefix}_)?{Y-m-d_His}_{uuid}(_{$postfix})?.{$extension}
     * ```
     *
     * Example:
     * ```
     * generateFileName('txt', 'my-file', 'the-end', '/')
     * ```
     *
     * Would produce something like:
     * ```
     * my-file_2019-05-22_124537_9336beee-7cf9-4228-85d8-9b34a2cd25ba_the-end.txt
     * ```
     *
     * @param  string  $extension  File extension
     * @param  string|null  $prefix  [optional] string to add before the generated filename
     * @param  string|null  $postfix  [optional] string to add after the generated filename (Before the extension)
     * @param  string  $delimiter  [optional], the delimiter to separate filename sections
     *
     * @return string
     */
    public static function generateFileName(
        string $extension,
        ?string $prefix = null,
        ?string $postfix = null,
        string $delimiter = '_'
    ): string {
        $time = Carbon::now();
        $id = Str::uuid();

        $fileName = '';

        // add any prefixes
        if (!empty($prefix)) {
            $fileName .= $prefix . $delimiter;
        }

        // build the filename
        $fileName .= $time->format('Y-m-d') . $delimiter . $time->format('His') . $delimiter . $id;

        // add any postfixes
        if (!empty($postfix)) {
            $fileName .= $delimiter . $postfix;
        }

        // add the extension and return it
        return self::appendExtension($fileName, $extension);
    }

    /**
     * Adds the given extension to the end of the filename if it isn't already.
     *
     * If the filename has the same extension already, then it is not changed.
     *
     * If the filename has a different extension already, the new one is appended:
     * `appendExtension('my-file.txt', 'pdf') == 'my-file.txt.pdf'`
     *
     * @param  string  $fileName
     * @param  string  $extension  File extension with or without the preceding "."
     *
     * @return string
     */
    public static function appendExtension(string $fileName, string $extension): string
    {
        $extension = '.' . ltrim($extension, '.');

        return $fileName . (!Str::endsWith($fileName, $extension) ? $extension : '');
    }
}
