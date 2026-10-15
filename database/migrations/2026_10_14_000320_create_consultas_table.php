<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // La sala privada: nace cuando una tarotista toma un turno.
        Schema::create('consultas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique(); // va en el link: /c/{uuid}
            $table->foreignId('turno_id')->unique()->constrained('turnos')->cascadeOnDelete(); // un turno, una consulta
            $table->foreignId('tarotista_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('consultante_id')->constrained('users')->cascadeOnDelete();
            $table->enum('estado', ['activa', 'finalizada'])->default('activa');
            $table->timestamps();
            $table->timestamp('finalizada_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultas');
    }
};
