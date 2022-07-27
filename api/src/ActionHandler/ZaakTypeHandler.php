<?php

namespace App\ActionHandler;

use App\Entity\Action;

class ZaakTypeHandler implements ActionHandlerInterface
{

    public function __construct()
    {
    }

    public function __run(array $data): array
    {
        var_dump('running the ZaakTypeHandler!');

        return $data;
    }
}
