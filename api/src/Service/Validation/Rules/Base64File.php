<?php

namespace App\Service\Validation\Rules;

use Respect\Validation\Exceptions\ComponentException;
use Respect\Validation\Rules;
use Respect\Validation\Validatable;
use Respect\Validation\Validator;

final class Base64File extends Rules\AbstractRule
{
    /**
     * Used for Base64StringException
     *
     * @var string|null
     */
    private ?string $exceptionMessage = null;

    /**
     * @inheritDoc
     * @throws ComponentException
     */
    public function validate($input): bool
    {
        // todo: extension

        return Validator::keySet(
            new Rules\Key('filename', new Filename(), false),
            new Rules\Key('base64', new Base64String(), false)
        )->$this->validate($input);
    }

    /**
     * @return string|null
     */
    public function getExceptionMessage(): ?string
    {
        return $this->exceptionMessage;
    }

    /**
     * @param string $exceptionMessage
     * @return Validatable
     */
    public function setExceptionMessage(string $exceptionMessage): Validatable
    {
        $this->exceptionMessage = $exceptionMessage;

        return $this;
    }
}
