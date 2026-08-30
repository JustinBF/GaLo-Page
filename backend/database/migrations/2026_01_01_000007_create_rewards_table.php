<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rewards', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();

            // Separa la Tienda CE de la Tienda CO.
            $table->enum('currency', ['CE', 'CO']);
            $table->unsignedInteger('cost');
            $table->enum('category', ['pokemon', 'objeto', 'cosmetico', 'ascenso_rango', 'especial'])
                ->default('objeto');

            // Solo para premios de tipo ascenso_rango (tienda CO).
            $table->foreignId('grants_rank_id')->nullable()->constrained('ranks')->nullOnDelete();

            // PNG del premio, subido por el admin.
            $table->string('image_mime')->nullable();
            $table->longText('image_data')->nullable();

            // null = stock ilimitado
            $table->integer('stock')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['currency', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rewards');
    }
};
