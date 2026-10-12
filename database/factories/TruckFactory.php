<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TruckFactory extends Factory
{
    public function definition(): array
    {
        return ['plate' => fake()->unique()->bothify('??? ####'), 'brand' => 'Volvo', 'model' => 'FH 460', 'year' => 2022, 'capacity_tons' => 32, 'odometer' => 100000, 'fuel' => 'diesel', 'maintenance' => false];
    }
}
