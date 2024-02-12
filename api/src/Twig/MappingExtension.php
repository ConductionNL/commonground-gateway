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
            new TwigFunction('dotToObject', [MappingRuntime::class, 'dotToArray']),
            new TwigFunction('arrayValues', [MappingRuntime::class, 'arrayValues']),
            new TwigFunction('getObject', [MappingRuntime::class, 'getObject']),
        ];
    }
}
