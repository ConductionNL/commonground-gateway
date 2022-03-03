<?php

namespace App\Service\Validation\Exceptions;

use Respect\Validation\Exceptions\ValidationException;

final class Base64StringException extends ValidationException
{
    protected $defaultTemplates = [
        self::MODE_DEFAULT => [
            self::STANDARD => '{{name}} must be a base64-encoded string. {{exceptionMessage}}',
        ],
        self::MODE_NEGATIVE => [
            self::STANDARD => '{{name}} must not be a base64-encoded string. {{exceptionMessage}}',
        ],
    ];
}
