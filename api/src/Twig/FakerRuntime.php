<?php

namespace App\Twig;

use Faker\Factory;
use Faker\Generator;
use Twig\Extension\RuntimeExtensionInterface;

class FakerRuntime implements RuntimeExtensionInterface
{
    private Generator $faker;

    public function __construct()
    {
        $this->faker = Factory::create();
    }

    public function generateUuid(): string
    {
        return $this->faker->uuid();
    }
}
