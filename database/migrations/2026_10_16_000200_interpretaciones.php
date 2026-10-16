<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plan v3 §13.6: la interpretación de una lectura. El borrador lo ve solo la tarotista
        // (llega por su canal privado); lo que publica queda como mensaje del chat.
        Schema::table('lecturas', function (Blueprint $table) {
            $table->text('interpretacion_borrador')->nullable();
            $table->string('interpretacion_fuente', 10)->nullable(); // ia | base
            $table->timestamp('interpretacion_publicada_at')->nullable();
        });
        Schema::table('mensajes', function (Blueprint $table) {
            $table->enum('tipo', ['texto', 'sistema', 'pedido_servicio', 'pedido_aclaratoria', 'carta', 'carta_astral', 'interpretacion'])->change();
            $table->string('contenido', 4000)->change(); // la interpretación es más larga que un mensaje común
        });
    }

    public function down(): void
    {
        Schema::table('mensajes', function (Blueprint $table) {
            $table->enum('tipo', ['texto', 'sistema', 'pedido_servicio', 'pedido_aclaratoria', 'carta', 'carta_astral'])->change();
            $table->string('contenido', 1000)->change();
        });
        Schema::table('lecturas', function (Blueprint $table) {
            $table->dropColumn(['interpretacion_borrador', 'interpretacion_fuente', 'interpretacion_publicada_at']);
        });
    }
};
