<?php

namespace Database\Factories;

use App\Domain\Master\Models\Cabang;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cabang>
 */
class CabangFactory extends Factory
{
    protected $model = Cabang::class;

    public function definition(): array
    {
        return [
            'kode' => fake()->unique()->bothify('CB-###'),
            'nama' => 'Cabang '.fake()->unique()->city(),
            'aktif' => true,
        ];
    }
}
