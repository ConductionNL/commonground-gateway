<?php

namespace App\Service\Validation\Exceptions;

use Respect\Validation\Exceptions\ValidationException;

final class JsonLogicException extends ValidationException
{
    protected $defaultTemplates = [
        self::MODE_DEFAULT => [
            self::STANDARD => '{{name}} must conform to the following Json Logic: {{jsonLogic}}',
        ],
        self::MODE_NEGATIVE => [
            self::STANDARD => '{{name}} must not conform to the following Json Logic: {{jsonLogic}}',
        ],
    ];
}
