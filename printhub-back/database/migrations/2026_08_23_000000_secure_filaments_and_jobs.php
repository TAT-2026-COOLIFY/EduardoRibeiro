<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('filament_print_job', 'job_filaments');

        Schema::table('job_filaments', function (Blueprint $table) {
            $table->renameColumn('quantity_grams', 'weight_grams');
        });

        Schema::table('filaments', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('print_jobs', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('status');
        });

        Schema::table('job_filaments', function (Blueprint $table) {
            $table->unique(['print_job_id', 'filament_id']);
        });
    }

    public function down(): void
    {
        Schema::table('job_filaments', function (Blueprint $table) {
            $table->dropUnique(['print_job_id', 'filament_id']);
            $table->renameColumn('weight_grams', 'quantity_grams');
        });

        Schema::table('print_jobs', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });

        Schema::table('filaments', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->integer('user_id')->change();
        });

        Schema::rename('job_filaments', 'filament_print_job');
    }
};
