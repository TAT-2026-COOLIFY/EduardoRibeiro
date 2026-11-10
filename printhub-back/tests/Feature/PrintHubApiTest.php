<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrintHubApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Testa se um usuário cadastrado consegue se autenticar via API
     * enviando as credenciais corretas para o endpoint de login.
     */
    public function test_user_can_authenticate_via_api(): void
    {
        $user = User::factory()->create([
            'email' => 'tcc@printhub.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'tcc@printhub.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
    }

    /**
     * Testa se um usuário autenticado via Laravel Sanctum consegue
     * acessar com sucesso as rotas protegidas da API (como a listagem de filamentos).
     */
    public function test_authenticated_user_can_access_protected_routes(): void
    {
        $user = User::factory()->create();

        Filament::create([
            'user_id' => $user->id,
            'brand' => 'Voolt3D',
            'type' => 'PLA',
            'colorName' => 'Azul',
            'colorHex' => '#0000FF',
            'pricePerKg' => 89.90,
            'initialQuantity' => 1000,
            'currentQuantity' => 1000,
        ]);

        $response = $this->actingAs($user, 'sanctum')
                         ->getJson('/api/filaments');

        $response->assertOk()->assertJsonStructure([
            '*' => ['id', 'user_id', 'brand', 'type', 'colorName', 'colorHex'],
        ]);
    }

    public function test_me_requires_a_valid_token_and_returns_the_authenticated_user(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('id', $user->id);
    }

    public function test_user_can_update_own_filament(): void
    {
        $user = User::factory()->create();
        $filament = Filament::create([
            'user_id' => $user->id,
            'brand' => 'Voolt3D',
            'type' => 'PLA',
            'colorName' => 'Azul',
            'colorHex' => '#0000FF',
            'pricePerKg' => 89.90,
            'initialQuantity' => 1000,
            'currentQuantity' => 1000,
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/filaments/{$filament->id}", ['currentQuantity' => 850])
            ->assertOk()
            ->assertJsonPath('currentQuantity', '850.000');
    }

    public function test_job_belongs_to_its_owner_and_stock_is_changed_only_when_completed(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $filament = Filament::create([
            'user_id' => $user->id,
            'brand' => 'Voolt3D',
            'type' => 'PLA',
            'colorName' => 'Azul',
            'colorHex' => '#0000FF',
            'pricePerKg' => 89.90,
            'initialQuantity' => 1000,
            'currentQuantity' => 1000,
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/jobs', [
            'fileName' => 'peca.stl',
            'status' => 'BACKLOG',
            'filament_id' => $filament->id,
            'materialWeightGrams' => 50,
            'estimatedTimeMinutes' => 30,
            'estimatedCost' => 5.50,
            'quantity' => 1,
            'layers' => 20,
            'stl_file' => $this->validStl(),
        ]);

        $jobId = $response->assertCreated()->json('id');
        $this->assertDatabaseHas('filaments', ['id' => $filament->id, 'currentQuantity' => 1000]);

        $this->actingAs($otherUser, 'sanctum')->getJson("/api/jobs/{$jobId}")->assertNotFound();
        $this->actingAs($user, 'sanctum')->putJson("/api/jobs/{$jobId}", ['status' => 'COMPLETED'])->assertOk();
        $this->assertDatabaseHas('filaments', ['id' => $filament->id, 'currentQuantity' => 950]);
        $this->actingAs($user, 'sanctum')->deleteJson("/api/jobs/{$jobId}")->assertNoContent();
        $this->assertDatabaseHas('filaments', ['id' => $filament->id, 'currentQuantity' => 1000]);
    }

    public function test_user_cannot_use_another_users_filament_or_delete_a_filament_used_by_a_job(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $filament = Filament::create([
            'user_id' => $otherUser->id,
            'brand' => 'Voolt3D',
            'type' => 'PLA',
            'colorName' => 'Azul',
            'colorHex' => '#0000FF',
            'pricePerKg' => 89.90,
            'initialQuantity' => 1000,
            'currentQuantity' => 1000,
        ]);

        $this->actingAs($user, 'sanctum')->postJson('/api/jobs', [
            'fileName' => 'peca.stl',
            'status' => 'BACKLOG',
            'filament_id' => $filament->id,
            'materialWeightGrams' => 50,
            'estimatedTimeMinutes' => 30,
            'quantity' => 1,
            'layers' => 20,
            'stl_file' => $this->validStl(),
        ])->assertUnprocessable();

        $this->createJob($otherUser, $filament);

        $this->actingAs($otherUser, 'sanctum')
            ->deleteJson("/api/filaments/{$filament->id}")
            ->assertConflict();
    }

    public function test_completed_job_consumes_and_restores_stock_for_each_quantity(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $filament = Filament::create([
            'user_id' => $user->id,
            'brand' => 'Voolt3D',
            'type' => 'PLA',
            'colorName' => 'Azul',
            'colorHex' => '#0000FF',
            'pricePerKg' => 89.90,
            'initialQuantity' => 1000,
            'currentQuantity' => 1000,
        ]);

        $jobId = $this->actingAs($user, 'sanctum')->postJson('/api/jobs', [
            'fileName' => 'peca.stl',
            'status' => 'BACKLOG',
            'filament_id' => $filament->id,
            'materialWeightGrams' => 50,
            'estimatedTimeMinutes' => 30,
            'quantity' => 2,
            'layers' => 20,
            'stl_file' => $this->validStl(),
        ])->assertCreated()->json('id');

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/jobs/{$jobId}/status", ['status' => 'COMPLETED'])
            ->assertOk()
            ->assertJsonPath('status', 'COMPLETED');

        $this->assertDatabaseHas('filaments', ['id' => $filament->id, 'currentQuantity' => 900]);

        $this->actingAs($user, 'sanctum')->deleteJson("/api/jobs/{$jobId}")->assertNoContent();
        $this->assertDatabaseHas('filaments', ['id' => $filament->id, 'currentQuantity' => 1000]);
    }

    public function test_filament_cannot_start_with_more_current_stock_than_initial_stock(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/filaments', [
            'brand' => 'Voolt3D',
            'type' => 'PLA',
            'colorName' => 'Azul',
            'colorHex' => '#0000FF',
            'pricePerKg' => 89.90,
            'initialQuantity' => 500,
            'currentQuantity' => 600,
        ])->assertUnprocessable();
    }

    public function test_job_supports_multiple_filaments_and_consumes_each_stock(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $blue = Filament::create([
            'user_id' => $user->id,
            'brand' => 'Voolt3D',
            'type' => 'PLA',
            'colorName' => 'Azul',
            'colorHex' => '#0000FF',
            'pricePerKg' => 89.90,
            'initialQuantity' => 1000,
            'currentQuantity' => 1000,
        ]);
        $red = Filament::create([
            'user_id' => $user->id,
            'brand' => 'Voolt3D',
            'type' => 'PLA',
            'colorName' => 'Vermelho',
            'colorHex' => '#FF0000',
            'pricePerKg' => 99.90,
            'initialQuantity' => 1000,
            'currentQuantity' => 1000,
        ]);

        $response = $this->actingAs($user, 'sanctum')->post('/api/jobs', [
            'fileName' => 'duas-cores.stl',
            'status' => 'COMPLETED',
            'filaments' => [
                ['filament_id' => $blue->id, 'weight_grams' => 40],
                ['filament_id' => $red->id, 'weight_grams' => 60],
            ],
            'estimatedTimeMinutes' => 30,
            'quantity' => 2,
            'layers' => 20,
            'stl_file' => $this->validStl(),
        ], ['Accept' => 'application/json']);

        $jobId = $response->assertCreated()->assertJsonCount(2, 'filaments')->json('id');

        $this->assertDatabaseHas('filaments', ['id' => $blue->id, 'currentQuantity' => 920]);
        $this->assertDatabaseHas('filaments', ['id' => $red->id, 'currentQuantity' => 880]);
        $this->assertDatabaseCount('job_filaments', 2);

        $this->actingAs($user, 'sanctum')->deleteJson("/api/jobs/{$jobId}")->assertNoContent();
        $this->assertDatabaseHas('filaments', ['id' => $blue->id, 'currentQuantity' => 1000]);
        $this->assertDatabaseHas('filaments', ['id' => $red->id, 'currentQuantity' => 1000]);
    }

    public function test_completion_uses_updated_quantity_when_quantity_changes_with_status(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $filament = Filament::create([
            'user_id' => $user->id,
            'brand' => 'Voolt3D',
            'type' => 'PLA',
            'colorName' => 'Azul',
            'colorHex' => '#0000FF',
            'pricePerKg' => 89.90,
            'initialQuantity' => 1000,
            'currentQuantity' => 1000,
        ]);
        $jobId = $this->createJob($user, $filament);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/jobs/{$jobId}", ['quantity' => 3, 'status' => 'COMPLETED'])
            ->assertOk();

        $this->assertDatabaseHas('filaments', ['id' => $filament->id, 'currentQuantity' => 850]);
        $this->assertDatabaseHas('print_jobs', ['id' => $jobId, 'quantity' => 3, 'status' => 'COMPLETED']);
    }

    private function createJob(User $user, Filament $filament): int
    {
        return $this->actingAs($user, 'sanctum')->postJson('/api/jobs', [
            'fileName' => 'peca.stl',
            'status' => 'BACKLOG',
            'filament_id' => $filament->id,
            'materialWeightGrams' => 50,
            'estimatedTimeMinutes' => 30,
            'quantity' => 1,
            'layers' => 20,
            'stl_file' => $this->validStl(),
        ])->assertCreated()->json('id');
    }

    private function validStl(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('peca.stl', "solid peca\nfacet normal 0 0 0\nouter loop\nvertex 0 0 0\nvertex 1 0 0\nvertex 0 1 0\nendloop\nendfacet\nendsolid peca\n");
    }
}
