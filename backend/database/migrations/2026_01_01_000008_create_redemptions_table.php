<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('reward_id')->nullable()->constrained('rewards')->nullOnDelete();

            // Copia del nombre/coste al momento del canje: el premio puede
            // borrarse o cambiar de precio y el historial debe sobrevivir.
            $table->string('reward_name');
            $table->enum('currency', ['CE', 'CO']);
            $table->unsignedInteger('cost_paid');

            $table->enum('status', ['pendiente', 'entregado', 'cancelado'])->default('pendiente');
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redemptions');
    }
};
