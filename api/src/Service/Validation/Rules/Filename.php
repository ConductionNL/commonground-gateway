<?php

namespace App\Service\Validation\Rules;

use Respect\Validation\Rules\AbstractRule;
use Respect\Validation\Validator;

final class Filename extends AbstractRule
{
    //todo getter setter
    /**
     * @var string
     */
    private string $regex;

    /**
     * @param string|null $regex
     */
    public function __construct(?string $regex = '/^[\w,\s()~!@#$%^&*_+\-=\[\]{};\'.]{1,255}\.[A-Za-z0-9]{1,5}$/')
//    public function __construct(?string $regex = '/^[^\\/:*?\"<>|]{1,255}\.[A-Za-z0-9]{1,5}$/') #todo:
    {
        $this->regex = $regex;
    }

    /**
     * @inheritDoc
     */
    public function validate($input): bool
    {
        if (Validator::stringType()->validate($input) &&
            Validator::regex($this->regex)->validate($input)) {
            return true;
        }

        return false;
    }
}
