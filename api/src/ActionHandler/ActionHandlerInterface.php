<?php

namespace App\ActionHandler;

use App\Entity\Action;

interface ActionHandlerInterface
{
    public function __construct();

    public function __run(array $data): array;
}
