<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class DefaultsExtension extends AbstractExtension
{
    public function getFunctions()
    {
        return [
            new TwigFunction('selfUrl', [DefaultsRuntime::class, 'selfUrl']),
        ];
    }
}
