<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Filament;
use App\Models\PrintJob;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Cria o usuário administrador padrão para a API
        $user = User::firstOrCreate(
            ['email' => 'admin@printhub.com'],
            [
                'name' => 'Administrador PRINT hub',
                'password' => Hash::make('password123'),
            ]
        );

        // Cria os filamentos de exemplo preenchendo os campos obrigatórios
        $filament1 = Filament::firstOrCreate(
            ['brand' => 'Voolt3D'],
            [
                'user_id' => $user->id,
                'type' => 'PLA',
                'colorName' => 'Vermelho',
                'colorHex' => '#FF0000',
                'pricePerKg' => 89.90,
                'initialQuantity' => 1000,
                'currentQuantity' => 1000,
            ]
        );

        $filament2 = Filament::firstOrCreate(
            ['brand' => '3D Prime'],
            [
                'user_id' => $user->id,
                'type' => 'PETG',
                'colorName' => 'Preto',
                'colorHex' => '#000000',
                'pricePerKg' => 99.90,
                'initialQuantity' => 1000,
                'currentQuantity' => 1000,
            ]
        );

        // Cria o trabalho de impressão (Print Job) de exemplo
        $printJob = PrintJob::firstOrCreate(
            ['fileName' => 'suporte_fone.stl'],
            [
                'user_id' => $user->id,
                'file_path' => 'jobs/suporte_fone.stl',
                'status' => 'BACKLOG',
                'estimatedTimeMinutes' => 120,
                'estimatedCost' => 15.50,
                'quantity' => 1,
                'materialWeightGrams' => 45.5,
                'layers' => 150,
            ]
        );

        // Vincula o filamento ao trabalho de impressão na tabela pivot
        if (method_exists($printJob, 'filaments')) {
            $printJob->filaments()->syncWithoutDetaching([
                $filament1->id => ['weight_grams' => 45.5]
            ]);
        }
    }
}
