<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plan v3 §13.1: la pregunta de una lectura se elige de una lista (tema → pregunta).
        // Sin texto libre: nada de prompt injection hacia la IA, ni preguntas delicadas,
        // y las de sí o no siempre bien formuladas.
        Schema::create('temas', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique(); // amor | dinero_trabajo | bienestar | crecimiento | general
            $table->string('nombre', 60);
            $table->string('icono', 8);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('preguntas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tema_id')->constrained('temas')->cascadeOnDelete();
            $table->string('texto', 200);
            $table->json('servicios'); // en qué servicios se puede elegir: tres_cartas | si_no | cruz_celta
            $table->boolean('activa')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::table('lecturas', function (Blueprint $table) {
            $table->dropColumn('pregunta'); // la pregunta libre se reemplaza por pregunta_id
        });
        Schema::table('lecturas', function (Blueprint $table) {
            $table->foreignId('pregunta_id')->nullable()->after('tipo')->constrained('preguntas')->nullOnDelete();
        });

        // El pedido de servicio en el chat lleva la pregunta elegida (pasa a la lectura al aceptarlo).
        Schema::table('mensajes', function (Blueprint $table) {
            $table->foreignId('pregunta_id')->nullable()->after('lectura_id')->constrained('preguntas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mensajes', fn (Blueprint $table) => $table->dropConstrainedForeignId('pregunta_id'));
        Schema::table('lecturas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pregunta_id');
            $table->string('pregunta', 500)->nullable()->after('tipo');
        });
        Schema::dropIfExists('preguntas');
        Schema::dropIfExists('temas');
    }
};
