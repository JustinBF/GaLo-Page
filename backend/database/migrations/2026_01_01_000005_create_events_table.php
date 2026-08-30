<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['torneo', 'caza', 'sorteo', 'otro'])->default('torneo');
            $table->date('held_at');

            // Referencia visual para que el admin decida cuanto CE reparte.
            $table->enum('difficulty', ['baja', 'media', 'alta', 'extrema'])->default('media');

            // Valor del premio en Pokeyenes crudos (500000, 1500000...).
            $table->unsignedBigInteger('prize_value')->default(0);

            $table->foreignId('organizer_id')->nullable()->constrained('members')->nullOnDelete();
            // CO concedido: sugerido por co_rules, sobrescribible por el admin.
            $table->unsignedInteger('co_awarded')->default(0);
            $table->boolean('co_manual_override')->default(false);

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('held_at');
            $table->index('organizer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
