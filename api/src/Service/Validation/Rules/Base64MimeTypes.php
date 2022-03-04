<?php

namespace App\Service\Validation\Rules;

use Exception;
use Respect\Validation\Rules\AbstractRule;
use Respect\Validation\Validatable;

final class Base64MimeTypes extends AbstractRule
{
    /**
     * @var array
     */
    private array $allowedTypes;

    /**
     * Used for Base64StringException.
     *
     * @var string|null
     */
    private ?string $exceptionMessage = null;

    /**
     * It is recommended to use the Base64String validation rule before using this rule.
     *
     * @param array $allowedTypes
     */
    public function __construct(array $allowedTypes)
    {
        $this->allowedTypes = $allowedTypes;
    }

    /**
     * @inheritDoc
     */
    public function validate($input): bool
    {
        // todo: we could just move and merge all validations from here to the Base64String rule?

        // Get base64 from this input string
        $exploded_input = explode(',', $input);
        $base64 = end($exploded_input);

        try {
            // Use the base64 to open a file and get the mimeType
            $fileData = base64_decode($base64);
            $f = finfo_open();
            $mimeType = finfo_buffer($f, $fileData, FILEINFO_MIME_TYPE);
            finfo_close($f);
        } catch (Exception $exception) {
            $this->setExceptionMessage($exception->getMessage());

            return false;
        }

        return in_array($mimeType, $this->allowedTypes);
    }

    /**
     * @return array|null
     */
    public function getAllowedTypes(): ?array
    {
        return $this->allowedTypes;
    }

    /**
     * @param array $allowedTypes
     *
     * @return Validatable
     */
    public function setAllowedTypes(array $allowedTypes): Validatable
    {
        $this->allowedTypes = $allowedTypes;

        return $this;
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
     *
     * @return Validatable
     */
    public function setExceptionMessage(string $exceptionMessage): Validatable
    {
        $this->exceptionMessage = $exceptionMessage;

        return $this;
    }
}
