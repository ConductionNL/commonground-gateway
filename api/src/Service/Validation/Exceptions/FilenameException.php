<?php

namespace App\Service\Validation\Exceptions;

use Respect\Validation\Exceptions\ValidationException;

final class FilenameException extends ValidationException
{
    protected $defaultTemplates = [
        self::MODE_DEFAULT => [
            self::STANDARD => '{{name}} must be a filename. ({{name}} must be a string and match the following regex: {{regex}} )',
        ],
        self::MODE_NEGATIVE => [
            self::STANDARD => '{{name}} must not be a filename. ({{name}} must not be string and not match the following regex: {{regex}} )',
        ],
    ];
}
