<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El chat de la sala privada: mensajes de las dos, de sistema y pedidos de servicio.
        Schema::create('mensajes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consulta_id')->constrained('consultas')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // null = sistema
            $table->enum('tipo', ['texto', 'sistema', 'pedido_servicio', 'pedido_aclaratoria']);
            $table->string('contenido', 1000); // texto plano: se muestra con textContent, nunca innerHTML
            $table->enum('servicio', ['tres_cartas', 'si_no', 'cruz_celta', 'carta_astral'])->nullable();
            $table->enum('ancla_tipo', ['carta', 'planeta'])->nullable();
            $table->string('ancla_valor', 50)->nullable();
            $table->enum('estado_pedido', ['pendiente', 'aceptado', 'rechazado'])->nullable();
            $table->timestamps();

            $table->index(['consulta_id', 'id']); // el chat se lee en orden
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajes');
    }
};
