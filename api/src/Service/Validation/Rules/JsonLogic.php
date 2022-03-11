<?php

namespace App\Service\Validation\Rules;

use Exception;
use JWadhams\JsonLogic as jsonLogicLib;
use Respect\Validation\Rules\AbstractRule;

final class JsonLogic extends AbstractRule
{
    //todo getter setter
    /**
     * @var mixed
     */
    private $jsonLogic;

    /**
     * @param mixed $jsonLogic This should be a string or an array. When using a string {{input}} can be used to add the input anywhere in the string, You might need to surround this with quotation marks like this: "{{input}}"
     */
    public function __construct($jsonLogic)
    {
        $this->jsonLogic = $jsonLogic;
    }

    /**
     * @inheritDoc
     *
     * @throws Exception
     */
    public function validate($input): bool
    {
        if (is_string($this->jsonLogic)) {
            // todo, what if we can't cast $input to string? maybe use try catch?
            $this->jsonLogic = str_replace('{{input}}', (string) $input, $this->jsonLogic);
            $this->jsonLogic = json_decode($this->jsonLogic, true);
            $input = null;
        }
        if (is_array($this->jsonLogic) && jsonLogicLib::apply($this->jsonLogic, $input)) {
            return true;
        }

        return false;
    }

    /*
     * examples of how to use this Rule:
     *
     * With $jsonLogic as a string, in this example $input should be equal to "apples"
     * new App\Service\Validation\Rules\JsonLogic('{"==":["apples", "{{input}}"]}');
     *
     * With $jsonLogic as an array, in this example $input should be an array that has the key "int" with the value 12
     * new App\Service\Validation\Rules\JsonLogic(["==" => [ ["var" => "int"], 12 ]);
     * Input like this wil result in true:
     * {
     *   "test": "someRandomValue"
     *   "int": 12
     * }
     * Input like this wil result in false:
     * {
     *   "int": 11
     * }
     */
}
