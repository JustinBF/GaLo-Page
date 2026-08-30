<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_movements', function (Blueprint $table) {
            $table->id();

            // Texto libre: quien aporto no tiene por que estar en la web.
            $table->string('contributor_name', 60);
            // Positivo aporta, negativo retira. En Pokeyenes crudos.
            $table->bigInteger('amount');
            $table->string('description', 200);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_movements');
    }
};
