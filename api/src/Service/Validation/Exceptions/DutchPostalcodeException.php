<?php

namespace App\Service\Validation\Exceptions;

use Respect\Validation\Exceptions\ValidationException;

final class DutchPostalcodeException extends ValidationException
{
    protected $defaultTemplates = [
        self::MODE_DEFAULT => [
            self::STANDARD => '{{name}} must be a dutch postal code',
        ],
        self::MODE_NEGATIVE => [
            self::STANDARD => '{{name}} must not be a dutch postal code',
        ],
    ];
}
