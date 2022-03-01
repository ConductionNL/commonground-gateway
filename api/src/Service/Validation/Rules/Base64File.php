<?php

namespace App\Service\Validation\Rules;

use Respect\Validation\Exceptions\ComponentException;
use Respect\Validation\Rules;

final class Base64File extends Rules\AllOf
{
    /**
     * @throws ComponentException
     */
    public function __construct()
    {
        parent::__construct(
            new Rules\Key('filename', new Filename(), false),
            new Rules\Key('base64', new Base64String(), true)
        );
    }

    /**
     * @inheritDoc
     */
    public function validate($input): bool
    {
        // todo: extension
        if ($input == false) {
            return false;
        }

        return parent::validate($input);
    }
}
