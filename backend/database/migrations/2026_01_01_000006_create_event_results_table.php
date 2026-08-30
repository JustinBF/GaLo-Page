<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            // CE lo teclea el admin segun la dificultad del evento. Sin reglas automaticas.
            $table->integer('ce_awarded')->default(0);
            $table->timestamps();

            $table->unique(['event_id', 'position']);
            $table->unique(['event_id', 'member_id']);
            $table->index('member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_results');
    }
};
