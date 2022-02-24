<?php

namespace App\Service\Validation\Exceptions;

use Respect\Validation\Exceptions\NestedValidationException;

final class Base64SizeException extends NestedValidationException
{
    public const BOTH = 'both';
    public const LOWER = 'lower';
    public const GREATER = 'greater';

    /**
     * {@inheritDoc}
     */
    protected $defaultTemplates = [
        self::MODE_DEFAULT => [
            self::BOTH    => '{{name}} must be a file size between {{minSize}} bytes and {{maxSize}} bytes',
            self::LOWER   => '{{name}} is to small, file size must be greater than {{minSize}} bytes',
            self::GREATER => '{{name}} is to big, file size must be lower than {{maxSize}} bytes',
        ],
        self::MODE_NEGATIVE => [
            self::BOTH    => '{{name}} must not be a file size between {{minSize}} bytes and {{maxSize}} bytes',
            self::LOWER   => '{{name}} is to big, file size must not be greater than {{minSize}} bytes',
            self::GREATER => '{{name}} is to small, file size must not be lower than {{maxSize}} bytes',
        ],
    ];

    /**
     * {@inheritDoc}
     */
    protected function chooseTemplate(): string
    {
        if (!$this->getParam('minSize')) {
            return self::GREATER;
        }

        if (!$this->getParam('maxSize')) {
            return self::LOWER;
        }

        return self::BOTH;
    }
}
