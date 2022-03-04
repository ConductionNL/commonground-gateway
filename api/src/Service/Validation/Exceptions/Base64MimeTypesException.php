<?php

namespace App\Service\Validation\Exceptions;

use Respect\Validation\Exceptions\ValidationException;

final class Base64MimeTypesException extends ValidationException
{
    protected $defaultTemplates = [
        self::MODE_DEFAULT => [
            self::STANDARD => '{{name}} must be one of the following mime types: {{allowedTypes}}',
        ],
        self::MODE_NEGATIVE => [
            self::STANDARD => '{{name}} must not be one of the following mime types: {{allowedTypes}}',
        ],
    ];
}
