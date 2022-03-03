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
        $dutchPc4List = $this->getDutchPC4List();

        foreach ($dutchPc4List as $dutchPc4) {
            if ($dutchPc4 == $input) {
                return true;
            }
        }

        return false;
    }

    private function getDutchPC4List(): array
    {
        $file = fopen(dirname(__FILE__).'../../../csv/dutch_pc4.csv', 'r');

        $i = 0;
        $dutchPc4List = [];
        while (!feof($file)) {
            $line = fgetcsv($file);
            if ($i === 0) {
                $i++;
                continue;
            }
            if (isset($line[1])) {
                $dutchPc4List[] = $line[1];
            }
            $i++;
        }

        return $dutchPc4List;
    }
}
