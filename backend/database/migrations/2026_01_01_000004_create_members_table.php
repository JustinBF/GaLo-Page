<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('nick')->unique();

            // Rango de jugador: lo asigna el admin a mano.
            $table->foreignId('rank_id')->nullable()->constrained('ranks')->nullOnDelete();
            // Rango de organizador: solo se obtiene canjeando CO en la tienda.
            $table->foreignId('organizer_rank_id')->nullable()->constrained('ranks')->nullOnDelete();

            $table->boolean('is_player')->default(true);
            $table->boolean('is_organizer')->default(false);
            $table->boolean('is_active')->default(true);

            // Avatar PNG guardado en la propia DB (Render tiene disco efimero).
            $table->string('avatar_mime')->nullable();
            $table->longText('avatar_data')->nullable();

            // Saldos cacheados. La verdad esta en credit_transactions.
            $table->integer('ce_balance')->default(0);
            $table->integer('co_balance')->default(0);

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'is_player']);
            $table->index(['is_active', 'is_organizer']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
