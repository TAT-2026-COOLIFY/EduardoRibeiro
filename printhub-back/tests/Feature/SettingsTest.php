<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_save_and_read_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/settings', ['settings' => ['theme' => 'dark']])
            ->assertOk()
            ->assertJsonPath('settings.theme', 'dark');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('settings.theme', 'dark');
    }
}