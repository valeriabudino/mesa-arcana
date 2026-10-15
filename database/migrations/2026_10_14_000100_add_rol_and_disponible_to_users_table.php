<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('rol', ['tarotista', 'consultante'])
                ->default('consultante')
                ->after('email');
            // Solo tiene sentido para tarotistas: si está "disponible", puede tomar turnos.
            $table->boolean('disponible')->default(false)->after('rol');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['rol', 'disponible']);
        });
    }
};
