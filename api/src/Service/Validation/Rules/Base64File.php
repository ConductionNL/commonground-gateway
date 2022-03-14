<?php

namespace App\Service\Validation\Rules;

use Respect\Validation\Exceptions\ComponentException;
use Respect\Validation\Rules;

class Base64File extends Rules\AllOf
{
    /**
     * @throws ComponentException
     */
    public function __construct()
    {
        parent::__construct(
            new Rules\Key('filename', new Filename(), false),
            new Rules\Key('base64', new Base64String(), true),
            new Base64Extension()
        );
    }
}
