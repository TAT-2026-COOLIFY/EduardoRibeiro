<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('password_reset_codes');
    }

    public function down(): void
    {
        Schema::create('password_reset_codes', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('code_hash');
            $table->string('reset_token_hash')->nullable()->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }
};