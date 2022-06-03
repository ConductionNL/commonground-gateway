<?php

namespace App\Service\Validation\Exceptions;

use Respect\Validation\Exceptions\ValidationException;

/**
 * Copy from BSN Exception.
 */
final class RsinException extends ValidationException
{
    /**
     * {@inheritDoc}
     */
    protected $defaultTemplates = [
        self::MODE_DEFAULT => [
            self::STANDARD => '{{name}} must be a RSIN',
        ],
        self::MODE_NEGATIVE => [
            self::STANDARD => '{{name}} must not be a RSIN',
        ],
    ];
}
