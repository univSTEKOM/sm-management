<?php

namespace Database\Factories;

use App\Domain\Master\Models\KabupatenKota;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KabupatenKota>
 */
class KabupatenKotaFactory extends Factory
{
    protected $model = KabupatenKota::class;

    public function definition(): array
    {
        return [
            // 99xx never collides with the seeded BPS codes (33xx).
            'kode' => fake()->unique()->numerify('99##'),
            'nama' => 'Kota '.fake()->unique()->city(),
        ];
    }
}
