<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plan v3 §13.2: la billetera. El saldo nunca cambia sin un movimiento en la misma
        // transacción, así que el historial siempre explica cada crédito.
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('saldo_disponible')->default(0);
            $table->unsignedInteger('saldo_reservado')->default(0);
        });

        // Cada movimiento dice cuánto cambió lo disponible y lo reservado, y cómo quedaron.
        Schema::create('movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('tipo', ['regalo', 'compra', 'reserva', 'liberacion', 'cobro', 'ingreso', 'comision', 'retiro']);
            $table->integer('monto'); // cambio en lo disponible (+/-)
            $table->integer('monto_reservado')->default(0); // cambio en lo reservado (+/-)
            $table->unsignedInteger('saldo_disponible'); // cómo quedó
            $table->unsignedInteger('saldo_reservado');
            $table->nullableMorphs('referencia'); // la lectura (o, más adelante, el pago o el retiro)
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'id']);
        });

        Schema::table('lecturas', function (Blueprint $table) {
            $table->unsignedInteger('precio')->nullable(); // null: lectura de antes de la billetera
            $table->enum('estado_cobro', ['reservado', 'cobrado', 'liberado'])->nullable();
        });

        // Las consultantes que ya existían también reciben su regalo (con su movimiento).
        $regalo = config('creditos.regalo_inicial');
        if ($regalo > 0) {
            DB::table('users')->where('rol', 'consultante')->orderBy('id')->each(function (object $user) use ($regalo) {
                DB::table('users')->where('id', $user->id)->update(['saldo_disponible' => $regalo]);
                DB::table('movimientos')->insert([
                    'user_id' => $user->id, 'tipo' => 'regalo', 'monto' => $regalo,
                    'saldo_disponible' => $regalo, 'saldo_reservado' => 0, 'created_at' => now(),
                ]);
            });
        }
    }

    public function down(): void
    {
        Schema::table('lecturas', function (Blueprint $table) {
            $table->dropColumn(['precio', 'estado_cobro']);
        });
        Schema::dropIfExists('movimientos');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['saldo_disponible', 'saldo_reservado']);
        });
    }
};
