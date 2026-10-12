<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DriverFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->name(), 'license' => fake()->numerify('########'), 'category' => 'Profesional', 'phone' => '099123456', 'hired_on' => '2020-01-01'];
    }
}
