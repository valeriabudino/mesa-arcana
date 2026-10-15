<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // La cola real de la sala de espera. Reverb solo avisa que cambió; el orden vive acá.
        Schema::create('turnos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique(); // lo único que viaja en el canal público de la cola
            $table->foreignId('consultante_id')->constrained('users')->cascadeOnDelete();
            $table->string('motivo', 200)->nullable();
            $table->enum('estado', ['esperando', 'tomado', 'cancelado', 'ausente'])->default('esperando');
            $table->foreignId('tarotista_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tomado_at')->nullable();
            $table->timestamps();

            $table->index(['estado', 'created_at']); // "los que esperan, en orden de llegada"
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turnos');
    }
};
