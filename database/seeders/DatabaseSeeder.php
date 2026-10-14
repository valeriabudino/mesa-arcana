<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(CartaSeeder::class);

        // Tarotista de prueba (contraseña: password). Las consultantes no tienen cuenta: entran con su nombre.
        User::factory()->tarotista()->create(['name' => 'Valeria', 'email' => 'tarotista@mesa.test']);
    }
}
