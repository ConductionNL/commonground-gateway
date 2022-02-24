<?php

namespace App\Service\Validation\Rules;

use Exception;
use Respect\Validation\Rules\AbstractRule;
use Respect\Validation\Validator;

final class Base64String extends AbstractRule
{
    /**
     * @inheritDoc
     */
    public function validate($input): bool
    {
        // Example of how input should look like: data:text/plain;base64,ZGl0IGlzIGVlbiB0ZXN0IGRvY3VtZW50
        // Make sure we have a string as input
        if (!Validator::stringType()->validate($input)) {
            return false;
        }

        // Get base64 from this input string and validate it
        $exploded_input = explode(',', $input);
        $base64 = end($exploded_input);
        if (!Validator::base64()->validate($base64)) {
            return false;
        }

        try {
            // Get mimeType from the base64 input string and compare it with the mimeType we get from using the base64 to open a file and get the mimeType
            //todo
            $mimeType1 = 'todo';

            // Use the base64 to open a file and get the mimeType
            $fileData = base64_decode($base64);
            $f = finfo_open();
            $mimeType2 = finfo_buffer($f, $fileData, FILEINFO_MIME_TYPE);
            finfo_close($f);

//            if ($mimeType1 !== $mimeType2) {
//                return false;
//            }
        } catch (Exception $exception) {
//            var_dump(3);
            return false;
        }
//        var_dump($mimeType2);
//
//        var_dump(5);
        return true;
    }
}
