<?php

namespace Database\Factories;

use App\Models\Solicitante;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Solicitante>
 */
class SolicitanteFactory extends Factory
{
    protected $model = Solicitante::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
        ];
    }
}
