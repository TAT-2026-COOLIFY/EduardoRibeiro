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
        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();
            
            // Relacionamento com o usuário
            // Usamos foreignId para criar uma chave estrangeira de forma elegante
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            $table->string('fileName');

            $table->string('file_path');
            
            // Status do pedido de impressão
            $table->enum('status', ['BACKLOG', 'TODO', 'INPROGRESS', 'APPROVAL', 'COMPLETED'])->default('BACKLOG');
            
            // Tempo estimado (Ajustei para o nome que o erro indicou que você queria usar)
            $table->integer('estimatedTimeMinutes'); 
            
            $table->decimal('estimatedCost', 10, 2);
            $table->integer('quantity');
            $table->decimal('materialWeightGrams', 10, 2);
            $table->integer('layers');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
    }
};