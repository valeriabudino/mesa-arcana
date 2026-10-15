<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cada lectura que se hace dentro de una consulta (una activa a la vez).
        Schema::create('lecturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consulta_id')->constrained('consultas')->cascadeOnDelete();
            $table->enum('tipo', ['tres_cartas', 'si_no', 'cruz_celta', 'carta_astral']);
            $table->enum('estado', ['lista', 'eligiendo', 'revelando', 'abierta', 'esperando_datos', 'calculada', 'cerrada']);
            $table->string('pregunta', 500)->nullable();
            $table->unsignedTinyInteger('cartas_esperadas')->default(0); // las de la tirada + 1 por cada aclaratoria aceptada
            $table->unsignedTinyInteger('aclarando')->nullable();        // qué posición se está aclarando mientras se elige
            $table->json('orden_mazo')->nullable();       // solo tiradas. SECRETO: $hidden, nunca llega al navegador
            $table->json('datos_nacimiento')->nullable(); // solo carta astral: fecha, hora, ciudad_id
            $table->json('resultado')->nullable();        // solo carta astral: posiciones calculadas
            $table->timestamps();
            $table->timestamp('cerrada_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lecturas');
    }
};
