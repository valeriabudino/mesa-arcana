<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plan v3 §13.5: la carta del día. La fila recién existe cuando se revela:
        // antes no hay nada guardado que se pueda espiar.
        Schema::create('oraculos', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->unique(); // el día en Argentina
            $table->foreignId('carta_id')->constrained('cartas');
            $table->boolean('invertida');
            $table->string('mensaje', 1000);
            $table->timestamp('revelado_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oraculos');
    }
};
