<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sesion_cartas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesion_id')->constrained('sesiones')->cascadeOnDelete();
            $table->unsignedTinyInteger('posicion');        // 0..n-1 dentro de la tirada
            $table->unsignedTinyInteger('indice_abanico');  // 0..77: qué carta del abanico tocó la consultante
            $table->foreignId('carta_id')->constrained('cartas');
            $table->boolean('invertida')->default(false);
            $table->text('nota')->nullable();
            $table->timestamp('revelada_at')->nullable();
            $table->timestamps();

            // Red de seguridad en la base: ni dos cartas en la misma posición,
            // ni la misma carta del abanico elegida dos veces.
            $table->unique(['sesion_id', 'posicion']);
            $table->unique(['sesion_id', 'indice_abanico']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesion_cartas');
    }
};
