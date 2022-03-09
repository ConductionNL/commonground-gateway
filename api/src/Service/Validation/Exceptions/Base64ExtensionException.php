<?php

namespace App\Service\Validation\Exceptions;

use Respect\Validation\Exceptions\ValidationException;

final class Base64ExtensionException extends ValidationException
{
    protected $defaultTemplates = [
        self::MODE_DEFAULT => [
            self::STANDARD => '{{name}} extension ({{extension}}) should match mime type ({{mime_type}})',
        ],
        self::MODE_NEGATIVE => [
            self::STANDARD => '{{name}} extension ({{extension}}) should not match mime type ({{mime_type}})',
        ],
    ];
}
