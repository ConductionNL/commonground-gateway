<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class MappingExtension extends AbstractExtension
{
    public function getFunctions()
    {
        return [
            new TwigFunction('map', [MappingRuntime::class, 'map']),
        ];
    }
}
