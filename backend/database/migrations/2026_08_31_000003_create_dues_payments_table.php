<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuotas semanales. Una fila = un jugador que ya pago una semana concreta.
 *
 * week_start es siempre el lunes de la semana ISO, asi la clave unica evita
 * cobrar dos veces lo mismo. Si no hay fila, esa semana esta pendiente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dues_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->date('week_start');
            $table->unsignedBigInteger('amount');

            // El cobro entra en el banco solo: guardamos cual movimiento fue
            // para poder retirarlo si se desmarca el check.
            $table->foreignId('bank_movement_id')
                ->nullable()
                ->constrained('bank_movements')
                ->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['member_id', 'week_start']);
            $table->index('week_start');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dues_payments');
    }
};
