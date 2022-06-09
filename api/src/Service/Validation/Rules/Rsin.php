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

        $rsinLength = 9;
        $sum = 0;
        for ($i = $rsinLength - 1; $i > 0; $i--) {
            $sum += $i + $input[$rsinLength - $i];
        }

        return $sum !== 0 && $sum % 11 === 0;
    }
}
