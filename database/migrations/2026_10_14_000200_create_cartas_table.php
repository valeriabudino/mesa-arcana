<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cartas', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('numero'); // mayores 0..21 · menores 1..14
            $table->string('nombre')->unique();
            $table->enum('arcano', ['mayor', 'menor']);
            $table->enum('palo', ['bastos', 'copas', 'espadas', 'oros'])->nullable(); // null en los mayores
            $table->string('imagen'); // ruta relativa a public/, ej: cartas/mayor-00.webp
            $table->text('significado_derecho');
            $table->text('significado_invertido');
            $table->json('palabras_clave');
            $table->enum('si_no', ['si', 'no', 'quizas']); // qué responde en una tirada de sí o no
            $table->timestamps();

            $table->unique(['palo', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cartas');
    }
};
