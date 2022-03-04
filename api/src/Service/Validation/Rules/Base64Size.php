<?php

namespace App\Service\Validation\Rules;

use Exception;
use Respect\Validation\Rules\AbstractRule;

final class Base64Size extends AbstractRule
{
    //todo getter setter
    /**
     * @var int|null
     */
    private ?int $minSize;

    //todo getter setter
    /**
     * @var int|null
     */
    private ?int $maxSize;

    /**
     * It is recommended to use the Base64String validation rule before using this rule.
     *
     * @param int|null $minSize
     * @param int|null $maxSize
     */
    public function __construct(?int $minSize, ?int $maxSize)
    {
        $this->minSize = $minSize;
        $this->maxSize = $maxSize;
    }

    /**
     * @inheritDoc
     */
    public function validate($input): bool
    {
        // todo: we could just move and merge all validations from here to the Base64String rule?

        $size = $this->getBase64Size($input);
        if ($this->isValidSize($size)) {
            return true;
        }

        return false;
    }

    /**
     * Gets the memory size of a base64 file in bytes.
     *
     * @param $base64
     *
     * @return Exception|float|int
     */
    private function getBase64Size($base64)
    { //return memory size in B (KB, MB)
        try {
            $size_in_bytes = (int) (strlen(rtrim($base64, '=')) * 3 / 4);
            // be careful when changing this!
//            $size_in_kb = $size_in_bytes / 1024;
//            $size_in_mb = $size_in_kb / 1024;

            return $size_in_bytes;
        } catch (Exception $e) {
            return $e;
        }
    }

    /**
     * Checks if given size is a valid size compared to the min & max size.
     *
     * @param int $size
     *
     * @return bool
     */
    private function isValidSize(int $size): bool
    {
        if ($this->minSize !== null && $this->maxSize !== null) {
            return $size >= $this->minSize && $size <= $this->maxSize;
        }

        if ($this->minSize !== null) {
            return $size >= $this->minSize;
        }

        return $size <= $this->maxSize;
    }
}
