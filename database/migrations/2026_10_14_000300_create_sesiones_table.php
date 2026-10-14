<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sesiones', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique(); // va en el link: /s/{uuid}
            $table->foreignId('tarotista_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('consultante_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('tipo_tirada', ['tres_cartas', 'si_no', 'cruz_celta']);
            $table->string('pregunta', 500)->nullable();
            $table->enum('estado', ['esperando', 'lista', 'eligiendo', 'revelando', 'finalizada'])
                ->default('esperando');
            $table->json('orden_mazo')->nullable(); // SECRETO: $hidden en el modelo, nunca llega al navegador
            $table->timestamps();
            $table->timestamp('finalizada_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesiones');
    }
};
