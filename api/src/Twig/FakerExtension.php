<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class FakerExtension extends AbstractExtension
{
    public function getFunctions()
    {
        return [
            new TwigFunction('generated_uuid', [FakerRuntime::class, 'generateUuid']),
        ];
    }
}
