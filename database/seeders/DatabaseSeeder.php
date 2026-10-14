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

        // Usuarias de prueba (contraseña: password)
        User::factory()->tarotista()->create(['name' => 'Valeria', 'email' => 'tarotista@mesa.test']);
        User::factory()->consultante()->create(['name' => 'María', 'email' => 'consultante@mesa.test']);
    }
}
