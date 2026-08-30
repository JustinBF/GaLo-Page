<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ranks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedTinyInteger('level');
            $table->enum('scope', ['player', 'organizer', 'both'])->default('both');
            $table->string('color_hex', 7)->default('#8b5cf6');
            $table->string('icon_mime')->nullable();
            // PNG en base64: evita el binding PARAM_LOB que BYTEA exige en PDO.
            $table->longText('icon_data')->nullable();
            $table->timestamps();

            $table->index(['scope', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ranks');
    }
};
