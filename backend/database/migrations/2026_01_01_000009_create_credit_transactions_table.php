<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->enum('currency', ['CE', 'CO']);
            // Positivo suma, negativo resta. El saldo es la suma de estas filas.
            $table->integer('amount');
            $table->enum('reason', [
                'event_win',
                'event_organized',
                'redemption',
                'manual_adjust',
                'correction',
            ]);

            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->foreignId('redemption_id')->nullable()->constrained('redemptions')->nullOnDelete();

            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['member_id', 'currency']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
