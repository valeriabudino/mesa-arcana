<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Para la carta astral: dónde nació la consultante (coordenadas + zona horaria histórica).
        Schema::create('ciudades', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('provincia');
            $table->decimal('lat', 8, 5);
            $table->decimal('lng', 8, 5);
            $table->string('zona_horaria'); // IANA, ej: America/Argentina/Cordoba
            $table->timestamps();

            $table->unique(['nombre', 'provincia']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ciudades');
    }
};
