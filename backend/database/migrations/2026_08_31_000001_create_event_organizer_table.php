<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un evento puede tener varios organizadores y el CO se reparte entre ellos.
 *
 * events.organizer_id se queda donde esta, sin usarse: los datos ya cargados
 * en produccion se copian aqui y la columna sirve de red por si hay que
 * revertir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_organizer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            // Parte del CO que le toca. El reparto es equitativo, pero el
            // resto de la division se asigna a los primeros, asi que las
            // partes no siempre son iguales al peso.
            $table->unsignedInteger('co_share')->default(0);
            $table->timestamps();

            $table->unique(['event_id', 'member_id']);
        });

        // Los eventos que ya existen conservan su organizador unico.
        DB::table('events')
            ->whereNotNull('organizer_id')
            ->orderBy('id')
            ->each(function ($event) {
                DB::table('event_organizer')->insert([
                    'event_id' => $event->id,
                    'member_id' => $event->organizer_id,
                    'co_share' => $event->co_awarded,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_organizer');
    }
};
