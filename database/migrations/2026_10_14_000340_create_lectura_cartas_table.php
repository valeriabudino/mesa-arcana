<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lectura_cartas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lectura_id')->constrained('lecturas')->cascadeOnDelete();
            $table->unsignedTinyInteger('posicion');        // 0..n-1 dentro de la tirada (las aclaratorias siguen numerando)
            $table->unsignedTinyInteger('indice_abanico');  // 0..77: qué carta del abanico tocó la consultante
            $table->foreignId('carta_id')->constrained('cartas');
            $table->boolean('invertida')->default(false);
            $table->boolean('es_aclaratoria')->default(false);
            $table->unsignedTinyInteger('aclara_posicion')->nullable(); // a qué carta aclara
            $table->text('nota')->nullable();
            $table->timestamp('revelada_at')->nullable();
            $table->timestamps();

            // Red de seguridad en la base: ni dos cartas en la misma posición,
            // ni la misma carta del abanico elegida dos veces.
            $table->unique(['lectura_id', 'posicion']);
            $table->unique(['lectura_id', 'indice_abanico']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lectura_cartas');
    }
};
