<?php

namespace App\Service\Validation\Rules;

use function ctype_digit;
use function mb_strlen;
use Respect\Validation\Rules\AbstractRule;

/**
 * Copy from BSN Rule.
 */
final class Rsin extends AbstractRule
{
    /**
     * {@inheritDoc}
     */
    public function validate($input): bool
    {
        if (!ctype_digit($input)) {
            return false;
        }

        if (mb_strlen($input) !== 9) {
            return false;
        }

        $sum = 0;
        for ($i = 9; $i >= 1; --$i) {
            $sum += $i * $input[9 - $i];
        }

        return $sum !== 0 && $sum % 11 === 0;
    }
}
