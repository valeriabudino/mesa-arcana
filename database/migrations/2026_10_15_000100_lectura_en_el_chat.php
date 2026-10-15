<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // La lectura vive en el chat: cada carta revelada y la carta astral calculada son
        // un mensaje (que apunta a su lectura). Y la consultante puede cancelar su pedido.
        Schema::table('mensajes', function (Blueprint $table) {
            $table->foreignId('lectura_id')->nullable()->after('user_id')->constrained('lecturas')->cascadeOnDelete();
            $table->enum('tipo', ['texto', 'sistema', 'pedido_servicio', 'pedido_aclaratoria', 'carta', 'carta_astral'])->change();
            $table->enum('estado_pedido', ['pendiente', 'aceptado', 'rechazado', 'cancelado'])->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('mensajes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lectura_id');
            $table->enum('tipo', ['texto', 'sistema', 'pedido_servicio', 'pedido_aclaratoria'])->change();
            $table->enum('estado_pedido', ['pendiente', 'aceptado', 'rechazado'])->nullable()->change();
        });
    }
};
