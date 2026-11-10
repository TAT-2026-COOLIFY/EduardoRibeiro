<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('filaments', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('brand');
            $table->string('type');
            $table->string('colorName');
            $table->string('colorHex');
            $table->decimal('pricePerKg', 10, 2);
            $table->decimal('initialQuantity', 10, 3);
            $table->decimal('currentQuantity', 10, 3);
            $table->integer('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('filaments');
    }
};
