<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('co_rules', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            // Valor del premio en Pokeyenes crudos. max_prize_value null = sin techo.
            $table->unsignedBigInteger('min_prize_value');
            $table->unsignedBigInteger('max_prize_value')->nullable();
            $table->unsignedInteger('co_amount');
            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('co_rules');
    }
};
