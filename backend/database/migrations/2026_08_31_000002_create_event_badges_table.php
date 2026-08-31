<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Insignias propias de cada evento.
 *
 * position null = insignia general del evento, la lucen todos los que
 * estuvieron en el podio. Con position 1, 2 o 3 se define una especifica
 * para ese puesto, que manda sobre la general.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position')->nullable();
            $table->string('mime');
            // PNG en base64, igual que los iconos de rango: evita el binding
            // PARAM_LOB que BYTEA exige en PDO.
            $table->longText('data');
            $table->timestamps();

            $table->unique(['event_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_badges');
    }
};
