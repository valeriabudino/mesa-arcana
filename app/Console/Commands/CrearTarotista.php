<?php

namespace App\Console\Commands;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Alta de tarotistas. No hay registro público: las cuentas se crean desde acá.
 */
class CrearTarotista extends Command
{
    protected $signature = 'tarotista:crear
        {--name= : Nombre}
        {--email= : Email}
        {--password= : Contraseña (si no se pasa, se pide sin mostrarla)}';

    protected $description = 'Crea una cuenta de tarotista';

    public function handle(): int
    {
        $datos = [
            'name' => $this->option('name') ?? text('Nombre', required: true),
            'email' => $this->option('email') ?? text('Email', required: true),
            'password' => $this->option('password') ?? password('Contraseña', required: true),
        ];

        $validador = Validator::make($datos, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $tarotista = new User($datos);
        $tarotista->rol = Rol::Tarotista;
        $tarotista->save();

        $this->info("Tarotista creada: {$tarotista->name} <{$tarotista->email}>");

        return self::SUCCESS;
    }
}
