<?php

namespace App\Service\Validation\Rules;

use Respect\Validation\Rules\AbstractRule;

final class DutchPostalcode extends AbstractRule
{
    /**
     * @inheritDoc
     */
    public function validate($input): bool
    {
        $dutch_pc4_list = $this->getDutchPC4List();

        foreach ($dutch_pc4_list as $dutch_pc4) {
            if ($dutch_pc4 == $input) {
                return true;
            }
        }

        return false;
    }

    private function getDutchPC4List(): array
    {
        $file = fopen(dirname(__FILE__).'../../../csv/dutch_pc4.csv', 'r');

        $i = 0;
        $dutch_pc4_list = [];
        while (!feof($file)) {
            $line = fgetcsv($file);
            if ($i === 0) {
                $i++;
                continue;
            }
            if (isset($line[1])) {
                $dutch_pc4_list[] = $line[1];
            }
            $i++;
        }

        return $dutch_pc4_list;
    }
}
