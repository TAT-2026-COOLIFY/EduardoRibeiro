<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('filament_print_job', function (Blueprint $table) {
            $table->id();

            $table->foreignId('filament_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->foreignId('print_job_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->decimal('quantity_grams', 8, 2);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filament_print_job');
    }
};
